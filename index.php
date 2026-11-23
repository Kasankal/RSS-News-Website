<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/feeds.php';

$user = require_login();
refresh_stale_feeds();

$topics = db()->query('SELECT id, name, slug FROM topics ORDER BY name')->fetchAll();
$slug = trim((string) ($_GET['topic'] ?? ''));
$current = null;
foreach ($topics as $topic) {
    if ($topic['slug'] === $slug) {
        $current = $topic;
        break;
    }
}

$latest = db()->query(
    'SELECT id, title, published_at FROM articles
     WHERE hidden_at IS NULL AND status = \'published\'
     ORDER BY published_at DESC
     LIMIT 8'
)->fetchAll();

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];
if ($q !== '') {
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    $search = db()->prepare(
        'SELECT id, title, body, published_at FROM articles
         WHERE hidden_at IS NULL AND status = \'published\'
           AND (title LIKE ? ESCAPE \'\\\\\' OR body LIKE ? ESCAPE \'\\\\\')
         ORDER BY published_at DESC
         LIMIT 20'
    );
    $search->execute([$like, $like]);
    $results = $search->fetchAll();
}

$sectionTopics = $current !== null ? [$current] : $topics;
$limit = $current !== null ? 5 : 3;
$sections = [];
$sectionQuery = db()->prepare(
    'SELECT a.id, a.kind, a.title, a.body, a.image_url, a.published_at, u.username, f.title AS feed_title
     FROM articles a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN feeds f ON f.id = a.feed_id
     WHERE a.hidden_at IS NULL AND a.status = \'published\' AND a.topic_id = ?
     ORDER BY a.published_at DESC
     LIMIT ' . $limit
);
foreach ($sectionTopics as $topic) {
    $sectionQuery->execute([(int) $topic['id']]);
    $rows = $sectionQuery->fetchAll();
    if ($rows !== []) {
        $sections[] = ['topic' => $topic, 'stories' => $rows];
    }
}

$title = $q !== '' ? 'Search' : ($current['name'] ?? 'Home');
$mode = 'app';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/topbar.php';
?>
<div class="board">
    <div class="board-main">
        <p class="kicker">The line</p>
        <h1><?= e($q !== '' ? 'Search' : ($current['name'] ?? 'Latest')) ?></h1>
        <nav class="topics">
            <a href="index.php"<?= $current === null ? ' class="on"' : '' ?>>All</a>
            <?php foreach ($topics as $topic): ?>
                <a href="index.php?topic=<?= e((string) $topic['slug']) ?>"<?= $current && (int) $current['id'] === (int) $topic['id'] ? ' class="on"' : '' ?>><?= e((string) $topic['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <?php if ($q !== ''): ?>
            <p class="lede"><?= $results === [] ? 'No stories match that search.' : e($q) ?></p>
            <div class="results">
                <?php foreach ($results as $item): ?>
                    <article>
                        <h3><a href="article.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a></h3>
                        <p class="meta"><?= e(date('j M Y', strtotime((string) $item['published_at']))) ?></p>
                        <p><?= e(clip((string) $item['body'], 180)) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($q === '' && $sections === []): ?>
            <p class="lede">Nothing in this section yet.</p>
        <?php endif; ?>
        <?php foreach ($q === '' ? $sections : [] as $section): ?>
            <?php
            $lead = $section['stories'][0];
            $minors = array_slice($section['stories'], 1);
            $leadImage = image_src($lead['image_url'] !== null ? (string) $lead['image_url'] : null);
            ?>
            <section class="band">
                <h2><a href="index.php?topic=<?= e((string) $section['topic']['slug']) ?>"><?= e((string) $section['topic']['name']) ?></a></h2>
                <div class="band-grid">
                    <article class="lead">
                        <?php if ($leadImage !== null): ?>
                            <a href="article.php?id=<?= (int) $lead['id'] ?>"><img src="<?= e($leadImage) ?>" alt=""></a>
                        <?php endif; ?>
                        <h3><a href="article.php?id=<?= (int) $lead['id'] ?>"><?= e((string) $lead['title']) ?></a></h3>
                        <p class="meta">
                            <?= e($lead['kind'] === 'original' ? (string) $lead['username'] : (string) ($lead['feed_title'] ?: 'Feed')) ?>
                            · <?= e(date('j M Y', strtotime((string) $lead['published_at']))) ?>
                        </p>
                        <p><?= e(clip((string) $lead['body'], 180)) ?></p>
                    </article>
                    <?php if ($minors !== []): ?>
                        <div class="minors">
                            <?php foreach ($minors as $minor): ?>
                                <?php $minorImage = image_src($minor['image_url'] !== null ? (string) $minor['image_url'] : null); ?>
                                <article class="minor">
                                    <?php if ($minorImage !== null): ?>
                                        <a href="article.php?id=<?= (int) $minor['id'] ?>"><img src="<?= e($minorImage) ?>" alt=""></a>
                                    <?php endif; ?>
                                    <h3><a href="article.php?id=<?= (int) $minor['id'] ?>"><?= e((string) $minor['title']) ?></a></h3>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
    <aside class="rail">
        <section class="find">
            <h2>Search</h2>
            <form method="get" action="index.php">
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search stories" aria-label="Search stories">
                <button type="submit">Search</button>
            </form>
        </section>
        <h2>Latest</h2>
        <?php if ($latest === []): ?>
            <p class="lede">No stories yet.</p>
        <?php endif; ?>
        <ol>
            <?php foreach ($latest as $item): ?>
                <li>
                    <a href="article.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a>
                    <span><?= e(date('j M', strtotime((string) $item['published_at']))) ?></span>
                </li>
            <?php endforeach; ?>
        </ol>
    </aside>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
