<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

$user = require_login();

$saved = db()->prepare(
    'SELECT a.id, a.title, a.published_at
     FROM bookmarks b
     JOIN articles a ON a.id = b.article_id
     WHERE b.user_id = ? AND a.hidden_at IS NULL AND a.status = \'published\'
     ORDER BY b.created_at DESC'
);
$saved->execute([(int) $user['id']]);
$bookmarks = $saved->fetchAll();

$starredQuery = db()->prepare(
    'SELECT a.id, a.title, a.published_at
     FROM stars s
     JOIN articles a ON a.id = s.article_id
     WHERE s.user_id = ? AND a.hidden_at IS NULL AND a.status = \'published\'
     ORDER BY s.created_at DESC'
);
$starredQuery->execute([(int) $user['id']]);
$starred = $starredQuery->fetchAll();

$mineQuery = db()->prepare(
    'SELECT id, title, status, published_at
     FROM articles
     WHERE user_id = ? AND hidden_at IS NULL
     ORDER BY created_at DESC'
);
$mineQuery->execute([(int) $user['id']]);
$mine = $mineQuery->fetchAll();

$title = 'Profile';
$mode = 'app';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/topbar.php';

function story_list(array $rows, string $empty): void
{
    if ($rows === []) {
        echo '<p class="lede">' . e($empty) . '</p>';
        return;
    }
    echo '<ul class="pile">';
    foreach ($rows as $row) {
        $status = isset($row['status']) && $row['status'] !== 'published' ? ' · ' . $row['status'] : '';
        echo '<li><a href="article.php?id=' . (int) $row['id'] . '">' . e((string) $row['title']) . '</a>';
        echo '<span>' . e(date('j M Y', strtotime((string) $row['published_at'])) . $status) . '</span></li>';
    }
    echo '</ul>';
}
?>
<main class="home">
    <p class="back"><a href="index.php">Back to homepage</a></p>
    <p class="kicker">Profile</p>
    <h1><?= e((string) $user['username']) ?></h1>
    <p class="lede"><?= e((string) $user['email']) ?> · <?= e((string) $user['role']) ?></p>

    <h2>Bookmarks</h2>
    <?php story_list($bookmarks, 'No bookmarked articles yet.'); ?>

    <h2>Stars</h2>
    <?php story_list($starred, 'No starred articles yet.'); ?>

    <h2>Your articles</h2>
    <?php story_list($mine, 'You have not published an article yet.'); ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
