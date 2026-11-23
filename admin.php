<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/feeds.php';

$user = require_role('admin');
$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'role') {
        $role = (string) ($_POST['role'] ?? '');
        $userId = (int) ($_POST['user_id'] ?? 0);
        if (!in_array($role, ['reader', 'writer', 'admin'], true)) {
            $errors[] = 'Choose a reader, writer, or admin role.';
        } elseif ($userId === (int) $user['id'] && $role !== 'admin') {
            $errors[] = 'Keep your own account as admin.';
        } else {
            $update = db()->prepare('UPDATE users SET role = ? WHERE id = ?');
            $update->execute([$role, $userId]);
            $notice = 'Role updated.';
        }
    }

    if ($action === 'topic') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = strtolower($name);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
        if ($name === '' || strlen($name) > 80 || $slug === '') {
            $errors[] = 'Give the section a short name.';
        } else {
            try {
                $insert = db()->prepare('INSERT INTO topics (name, slug) VALUES (?, ?)');
                $insert->execute([$name, $slug]);
                $notice = 'Section added.';
            } catch (PDOException $e) {
                $errors[] = 'That section already exists.';
            }
        }
    }

    if ($action === 'feed') {
        $url = trim((string) ($_POST['feed_url'] ?? ''));
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        $topic = db()->prepare('SELECT id FROM topics WHERE id = ?');
        $topic->execute([$topicId]);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            $errors[] = 'Enter a full http or https feed address.';
        } elseif (!$topic->fetch()) {
            $errors[] = 'Choose a section for this feed.';
        } else {
            $existing = db()->prepare('SELECT id FROM feeds WHERE url = ?');
            $existing->execute([$url]);
            $found = $existing->fetch();
            $feedId = $found ? (int) $found['id'] : 0;
            if ($feedId === 0) {
                $insert = db()->prepare('INSERT INTO feeds (title, url, topic_id) VALUES (?, ?, ?)');
                $insert->execute([clip(parse_url($url, PHP_URL_HOST) ?: 'Feed', 255), $url, $topicId]);
                $feedId = (int) db()->lastInsertId();
            } else {
                $update = db()->prepare('UPDATE feeds SET topic_id = ? WHERE id = ?');
                $update->execute([$topicId, $feedId]);
            }
            try {
                $count = fetch_feed($feedId);
                $notice = 'Fetched ' . $count . ' stories.';
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if ($action === 'refresh') {
        $feedId = (int) ($_POST['feed_id'] ?? 0);
        $topicId = (int) ($_POST['topic_id'] ?? 0);
        if ($topicId > 0) {
            $update = db()->prepare('UPDATE feeds SET topic_id = ? WHERE id = ?');
            $update->execute([$topicId, $feedId]);
        }
        try {
            $count = fetch_feed($feedId);
            $notice = 'Fetched ' . $count . ' stories.';
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if ($action === 'approve') {
        $approve = db()->prepare(
            'UPDATE articles SET status = \'published\', published_at = NOW() WHERE id = ? AND status = \'pending\''
        );
        $approve->execute([(int) ($_POST['article_id'] ?? 0)]);
        $notice = 'Article approved.';
    }

    if ($action === 'reject') {
        $reject = db()->prepare('UPDATE articles SET status = \'rejected\' WHERE id = ? AND status = \'pending\'');
        $reject->execute([(int) ($_POST['article_id'] ?? 0)]);
        $notice = 'Article declined.';
    }

    if ($action === 'hide') {
        $hide = db()->prepare('UPDATE articles SET hidden_at = NOW() WHERE id = ? AND hidden_at IS NULL');
        $hide->execute([(int) ($_POST['article_id'] ?? 0)]);
        $notice = 'Article removed from the line.';
    }
}

$people = db()->query('SELECT id, username, email, role FROM users ORDER BY username')->fetchAll();
$topics = db()->query('SELECT id, name, slug FROM topics ORDER BY name')->fetchAll();
$feeds = db()->query('SELECT id, title, url, topic_id FROM feeds ORDER BY title')->fetchAll();
$pending = db()->query(
    'SELECT a.id, a.title, u.username, t.name AS topic_name
     FROM articles a
     JOIN users u ON u.id = a.user_id
     LEFT JOIN topics t ON t.id = a.topic_id
     WHERE a.status = \'pending\'
     ORDER BY a.created_at'
)->fetchAll();
$stories = db()->query(
    'SELECT a.id, a.title, t.name AS topic_name
     FROM articles a
     LEFT JOIN topics t ON t.id = a.topic_id
     WHERE a.hidden_at IS NULL AND a.status = \'published\'
     ORDER BY a.published_at DESC
     LIMIT 30'
)->fetchAll();

$title = 'Admin';
$mode = 'app';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/topbar.php';
?>
<main class="home">
    <p class="back"><a href="index.php">Back to homepage</a></p>
    <p class="kicker">Admin</p>
    <h1>Desk</h1>
    <nav class="desk">
        <a href="#queue">Queue<?= $pending !== [] ? ' (' . count($pending) . ')' : '' ?></a>
        <a href="#people">People</a>
        <a href="#sections">Sections</a>
        <a href="#feeds">Feeds</a>
        <a href="#line">Line</a>
    </nav>
    <?php if ($notice !== ''): ?>
        <p class="notice"><?= e($notice) ?></p>
    <?php endif; ?>
    <?php if ($errors !== []): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <section class="block" id="queue">
        <h2>Queue</h2>
        <?php if ($pending === []): ?>
            <p class="lede">No posts waiting.</p>
        <?php endif; ?>
        <?php foreach ($pending as $story): ?>
            <form class="inline" method="post" action="admin.php#queue">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="article_id" value="<?= (int) $story['id'] ?>">
                <span>
                    <a href="article.php?id=<?= (int) $story['id'] ?>"><?= e((string) $story['title']) ?></a>
                    <span class="meta"><?= e((string) $story['username']) ?><?= $story['topic_name'] ? ' · ' . e((string) $story['topic_name']) : '' ?></span>
                </span>
                <button class="small" name="action" value="approve" type="submit">Approve</button>
                <button class="small ghost" name="action" value="reject" type="submit">Decline</button>
            </form>
        <?php endforeach; ?>
    </section>

    <section class="block" id="people">
        <h2>People</h2>
        <?php foreach ($people as $person): ?>
            <form class="inline" method="post" action="admin.php#people">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="user_id" value="<?= (int) $person['id'] ?>">
                <span><?= e((string) $person['username']) ?></span>
                <select name="role">
                    <?php foreach (['reader', 'writer', 'admin'] as $role): ?>
                        <option value="<?= $role ?>"<?= $person['role'] === $role ? ' selected' : '' ?>><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="small" type="submit">Save</button>
            </form>
        <?php endforeach; ?>
    </section>

    <section class="block" id="sections">
        <h2>Sections</h2>
        <form method="post" action="admin.php#sections">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="topic">
            <label>
                New section
                <input type="text" name="name" required maxlength="80" placeholder="Weather">
            </label>
            <button type="submit">Add section</button>
        </form>
        <?php foreach ($topics as $topic): ?>
            <p class="inline"><span><?= e((string) $topic['name']) ?></span></p>
        <?php endforeach; ?>
    </section>

    <section class="block" id="feeds">
        <h2>Feeds</h2>
        <form method="post" action="admin.php#feeds">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="feed">
            <label>
                Section
                <select name="topic_id" required>
                    <option value="">Choose</option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= (int) $topic['id'] ?>"><?= e((string) $topic['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                Feed address
                <input type="url" name="feed_url" required placeholder="https://example.com/rss.xml">
            </label>
            <button type="submit">Fetch</button>
        </form>
        <?php foreach ($feeds as $feed): ?>
            <form class="inline" method="post" action="admin.php#feeds">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="refresh">
                <input type="hidden" name="feed_id" value="<?= (int) $feed['id'] ?>">
                <span><?= e((string) $feed['title']) ?></span>
                <select name="topic_id">
                    <option value="">Section</option>
                    <?php foreach ($topics as $topic): ?>
                        <option value="<?= (int) $topic['id'] ?>"<?= (int) $feed['topic_id'] === (int) $topic['id'] ? ' selected' : '' ?>><?= e((string) $topic['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="small" type="submit">Refresh</button>
            </form>
        <?php endforeach; ?>
    </section>

    <section class="block" id="line">
        <h2>On the line</h2>
        <?php if ($stories === []): ?>
            <p class="lede">No articles yet.</p>
        <?php endif; ?>
        <?php foreach ($stories as $story): ?>
            <form class="inline" method="post" action="admin.php#line">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="hide">
                <input type="hidden" name="article_id" value="<?= (int) $story['id'] ?>">
                <span>
                    <a href="article.php?id=<?= (int) $story['id'] ?>"><?= e((string) $story['title']) ?></a>
                    <?php if ($story['topic_name']): ?>
                        <span class="meta"><?= e((string) $story['topic_name']) ?></span>
                    <?php endif; ?>
                </span>
                <button class="small" type="submit">Remove</button>
            </form>
        <?php endforeach; ?>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
