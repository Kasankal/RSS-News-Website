<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

$user = require_login();
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT a.id, a.kind, a.user_id, a.topic_id, a.title, a.body, a.url, a.image_url, a.status, a.published_at, a.hidden_at,
            u.username, f.title AS feed_title, t.name AS topic_name
     FROM articles a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN feeds f ON f.id = a.feed_id
     LEFT JOIN topics t ON t.id = a.topic_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$story = $stmt->fetch();

$owns = $story && (int) $story['user_id'] === (int) $user['id'];
$blocked = !$story
    || ($story['hidden_at'] !== null && $user['role'] !== 'admin')
    || ($story['status'] !== 'published' && !$owns && $user['role'] !== 'admin');
if ($blocked) {
    http_response_code(404);
    exit('That article is not on the line.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string) ($_POST['action'] ?? '');
    $table = $action === 'bookmark' ? 'bookmarks' : ($action === 'star' ? 'stars' : '');
    if ($table !== '') {
        $exists = db()->prepare("SELECT 1 FROM {$table} WHERE user_id = ? AND article_id = ?");
        $exists->execute([(int) $user['id'], $id]);
        if ($exists->fetch()) {
            $remove = db()->prepare("DELETE FROM {$table} WHERE user_id = ? AND article_id = ?");
            $remove->execute([(int) $user['id'], $id]);
        } else {
            $add = db()->prepare("INSERT INTO {$table} (user_id, article_id) VALUES (?, ?)");
            $add->execute([(int) $user['id'], $id]);
        }
    }
    header('Location: article.php?id=' . $id);
    exit;
}

$marked = db()->prepare('SELECT 1 FROM bookmarks WHERE user_id = ? AND article_id = ?');
$marked->execute([(int) $user['id'], $id]);
$bookmarked = (bool) $marked->fetch();
$starred = db()->prepare('SELECT 1 FROM stars WHERE user_id = ? AND article_id = ?');
$starred->execute([(int) $user['id'], $id]);
$isStarred = (bool) $starred->fetch();

$related = [];
if ($story['topic_id'] !== null) {
    $more = db()->prepare(
        'SELECT id, title, image_url FROM articles
         WHERE topic_id = ? AND id <> ? AND status = \'published\' AND hidden_at IS NULL
         ORDER BY published_at DESC
         LIMIT 3'
    );
    $more->execute([(int) $story['topic_id'], $id]);
    $related = $more->fetchAll();
}
$canEdit = $story['kind'] === 'original' && ($owns || $user['role'] === 'admin');

$image = image_src($story['image_url'] !== null ? (string) $story['image_url'] : null);
$title = (string) $story['title'];
$mode = 'app';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/topbar.php';
?>
<main class="home">
    <p class="back"><a href="index.php">Back to homepage</a></p>
    <?php if ($story['status'] === 'pending'): ?>
        <p class="notice">Waiting for an admin to approve this.</p>
    <?php elseif ($story['status'] === 'rejected'): ?>
        <p class="notice declined">An admin declined this article.</p>
    <?php endif; ?>
    <?php if ($image !== null): ?>
        <img class="hero" src="<?= e($image) ?>" alt="">
    <?php endif; ?>
    <p class="kicker"><?= e((string) ($story['topic_name'] ?: ($story['kind'] === 'original' ? 'Original' : 'Sourced'))) ?></p>
    <h1><?= e((string) $story['title']) ?></h1>
    <p class="meta">
        <?php if ($story['kind'] === 'original'): ?>
            <?= e((string) $story['username']) ?>
        <?php else: ?>
            <?= e((string) ($story['feed_title'] ?: 'Feed')) ?>
        <?php endif; ?>
        · <?= e(date('j M Y', strtotime((string) $story['published_at']))) ?>
    </p>
    <?php if ($canEdit): ?>
        <p class="back"><a href="write.php?id=<?= (int) $story['id'] ?>">Edit article</a></p>
    <?php endif; ?>
    <form class="saves" method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <button class="small" type="submit" name="action" value="bookmark"><?= $bookmarked ? 'Bookmarked' : 'Bookmark' ?></button>
        <button class="small ghost" type="submit" name="action" value="star"><?= $isStarred ? 'Starred' : 'Star' ?></button>
    </form>
    <div class="body"><?= nl2br(e((string) $story['body'])) ?></div>
    <?php if (!empty($story['url'])): ?>
        <p><a href="<?= e((string) $story['url']) ?>">Read at the source</a></p>
    <?php endif; ?>
    <?php if ($related !== []): ?>
        <section class="band">
            <h2>More in <?= e((string) ($story['topic_name'] ?: 'this section')) ?></h2>
            <div class="related">
                <?php foreach ($related as $item): ?>
                    <?php $shot = image_src($item['image_url'] !== null ? (string) $item['image_url'] : null); ?>
                    <article class="minor">
                        <?php if ($shot !== null): ?>
                            <a href="article.php?id=<?= (int) $item['id'] ?>"><img src="<?= e($shot) ?>" alt=""></a>
                        <?php endif; ?>
                        <h3><a href="article.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a></h3>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
