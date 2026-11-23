<?php
declare(strict_types=1);

function clip(string $value, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }

    return substr($value, 0, $max);
}

function plain_text(string $value): string
{
    $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

    return clip($text, 8000);
}

function feed_time(string $value): string
{
    $ts = strtotime($value);

    return date('Y-m-d H:i:s', $ts === false ? time() : $ts);
}

function fetch_feed(int $feedId): int
{
    $feed = db()->prepare('SELECT id, url, topic_id FROM feeds WHERE id = ?');
    $feed->execute([$feedId]);
    $row = $feed->fetch();
    if (!$row) {
        throw new RuntimeException('That feed is not on file.');
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 12,
            'user_agent' => 'RSS Newsline/1.0',
            'follow_location' => 1,
        ],
    ]);
    $xml = @file_get_contents((string) $row['url'], false, $context);
    if ($xml === false || trim($xml) === '') {
        throw new RuntimeException('Could not read that feed.');
    }

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml);
    if ($rss === false) {
        throw new RuntimeException('That address is not an RSS feed.');
    }

    $items = feed_entries($rss);
    if ($items === []) {
        throw new RuntimeException('No stories were found in that feed.');
    }

    $save = db()->prepare(
        'INSERT INTO articles (kind, feed_id, topic_id, external_id, title, body, url, image_url, status, published_at)
         VALUES (\'sourced\', ?, ?, ?, ?, ?, ?, ?, \'published\', ?)
         ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body), url = VALUES(url),
           image_url = VALUES(image_url), topic_id = VALUES(topic_id), published_at = VALUES(published_at)'
    );

    $count = 0;
    foreach (array_slice($items, 0, 25) as $item) {
        $save->execute([
            $feedId,
            $row['topic_id'] !== null ? (int) $row['topic_id'] : null,
            $item['external_id'],
            $item['title'],
            $item['body'],
            $item['url'],
            $item['image_url'],
            $item['published_at'],
        ]);
        $count++;
    }

    $stamp = db()->prepare('UPDATE feeds SET fetched_at = NOW() WHERE id = ?');
    $stamp->execute([$feedId]);

    return $count;
}

function refresh_stale_feeds(): void
{
    $due = db()->query(
        'SELECT id FROM feeds WHERE fetched_at IS NULL OR fetched_at < (NOW() - INTERVAL 1 DAY)'
    )->fetchAll();
    foreach ($due as $feed) {
        try {
            fetch_feed((int) $feed['id']);
        } catch (RuntimeException $e) {
            continue;
        }
    }
}

function feed_entries(SimpleXMLElement $rss): array
{
    $items = [];

    if (isset($rss->channel->item)) {
        foreach ($rss->channel->item as $item) {
            $link = trim((string) $item->link);
            $guid = trim((string) ($item->guid ?: $link));
            $title = plain_text((string) $item->title);
            if ($title === '' || $guid === '') {
                continue;
            }
            $image = item_image($item, (string) $item->description);
            $items[] = [
                'title' => clip($title, 500),
                'body' => plain_text((string) $item->description),
                'url' => $link !== '' ? clip($link, 2048) : null,
                'image_url' => $image === null ? null : sharper_image($image),
                'external_id' => clip($guid, 512),
                'published_at' => feed_time((string) $item->pubDate),
            ];
        }

        return $items;
    }

    $atom = $rss->children('http://www.w3.org/2005/Atom');
    $entries = isset($atom->entry) && count($atom->entry) ? $atom->entry : ($rss->entry ?? []);
    foreach ($entries as $entry) {
        $link = '';
        $links = $entry->children('http://www.w3.org/2005/Atom')->link ?? $entry->link;
        foreach ($links as $candidate) {
            $rel = xml_attr($candidate, 'rel');
            if ($rel === '' || $rel === 'alternate') {
                $link = xml_attr($candidate, 'href');
                break;
            }
        }
        $id = trim((string) ($entry->id ?: $link));
        $title = plain_text((string) $entry->title);
        if ($title === '' || $id === '') {
            continue;
        }
        $summary = (string) ($entry->summary ?: $entry->content);
        $when = (string) ($entry->published ?: $entry->updated);
        $image = item_image($entry, $summary);
        $items[] = [
            'title' => clip($title, 500),
            'body' => plain_text($summary),
            'url' => $link !== '' ? clip($link, 2048) : null,
            'image_url' => $image === null ? null : sharper_image($image),
            'external_id' => clip($id, 512),
            'published_at' => feed_time($when),
        ];
    }

    return $items;
}

function xml_attr(SimpleXMLElement $element, string $name): string
{
    $attrs = $element->attributes();

    return isset($attrs[$name]) ? trim((string) $attrs[$name]) : '';
}

function item_image(SimpleXMLElement $item, string $html): ?string
{
    $media = $item->children('http://search.yahoo.com/mrss/');
    if (isset($media->thumbnail)) {
        $thumb = http_url(xml_attr($media->thumbnail, 'url'));
        if ($thumb !== null) {
            return $thumb;
        }
    }
    if (isset($media->content)) {
        foreach ($media->content as $content) {
            $type = xml_attr($content, 'type');
            $url = http_url(xml_attr($content, 'url'));
            if ($url !== null && ($type === '' || str_starts_with($type, 'image/'))) {
                return $url;
            }
        }
    }
    if (isset($item->enclosure)) {
        $type = xml_attr($item->enclosure, 'type');
        $url = http_url(xml_attr($item->enclosure, 'url'));
        if ($url !== null && str_starts_with($type, 'image/')) {
            return $url;
        }
    }
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $match) === 1) {
        return http_url(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    return null;
}
