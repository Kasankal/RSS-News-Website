<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

$user = require_role('writer', 'admin');
$errors = [];
$articleTitle = '';
$body = '';
$imageUrl = '';
$topicId = 0;
$editId = (int) ($_GET['id'] ?? 0);
$topics = db()->query('SELECT id, name FROM topics ORDER BY name')->fetchAll();

$editing = null;
if ($editId > 0) {
    $load = db()->prepare(
        'SELECT id, user_id, kind, topic_id, title, body, image_url, status FROM articles WHERE id = ?'
    );
    $load->execute([$editId]);
    $editing = $load->fetch() ?: null;
    $allowed = $editing
        && $editing['kind'] === 'original'
        && ($user['role'] === 'admin' || (int) $editing['user_id'] === (int) $user['id']);
    if (!$allowed) {
        http_response_code(404);
        exit('That article is not yours to edit.');
    }
    $articleTitle = (string) $editing['title'];
    $body = (string) $editing['body'];
    $topicId = (int) $editing['topic_id'];
    $storedImage = (string) ($editing['image_url'] ?? '');
    $imageUrl = str_starts_with($storedImage, 'http://') || str_starts_with($storedImage, 'https://') ? $storedImage : '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $articleTitle = trim((string) ($_POST['title'] ?? ''));
    $body = trim((string) ($_POST['body'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $topicId = (int) ($_POST['topic_id'] ?? 0);
    $uploaded = store_image($_FILES['image_file'] ?? []);
    if ($uploaded === '') {
        $errors[] = 'Upload a JPG, PNG, WEBP, or GIF under 2 MB.';
    }
    if ($uploaded !== null && $uploaded !== '') {
        $image = $uploaded;
    } elseif ($imageUrl !== '') {
        $image = http_url($imageUrl);
    } else {
        $image = $editing['image_url'] ?? null;
    }

    if ($articleTitle === '' || strlen($articleTitle) > 500) {
        $errors[] = 'Give the article a title, up to 500 characters.';
    }
    if ($body === '') {
        $errors[] = 'Write the article before posting it.';
    }
    if ($imageUrl !== '' && $image === null) {
        $errors[] = 'Image address must be a full http or https link.';
    }
    $topicOk = false;
    foreach ($topics as $topic) {
        if ((int) $topic['id'] === $topicId) {
            $topicOk = true;
            break;
        }
    }
    if (!$topicOk) {
        $errors[] = 'Choose a section.';
    }

    if ($errors === []) {
        if ($editing) {
            $update = db()->prepare(
                'UPDATE articles SET topic_id = ?, title = ?, body = ?, image_url = ? WHERE id = ? AND kind = \'original\''
            );
            $update->execute([$topicId, $articleTitle, $body, $image, $editId]);
            header('Location: article.php?id=' . $editId);
            exit;
        }
        $status = $user['role'] === 'admin' ? 'published' : 'pending';
        $insert = db()->prepare(
            'INSERT INTO articles (kind, user_id, topic_id, title, body, image_url, status, published_at)
             VALUES (\'original\', ?, ?, ?, ?, ?, ?, NOW())'
        );
        $insert->execute([(int) $user['id'], $topicId, $articleTitle, $body, $image, $status]);
        header('Location: article.php?id=' . (int) db()->lastInsertId());
        exit;
    }
}

$mine = db()->prepare(
    'SELECT id, title, status, created_at FROM articles WHERE user_id = ? AND kind = \'original\' AND status <> \'published\' ORDER BY created_at DESC'
);
$mine->execute([(int) $user['id']]);
$mine = $mine->fetchAll();

$title = $editing ? 'Edit' : 'Write';
$mode = 'app';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/topbar.php';
?>
<main class="home">
    <p class="back"><a href="index.php">Back to homepage</a></p>
    <p class="kicker">Original</p>
    <h1><?= $editing ? 'Edit' : 'Write' ?></h1>
    <p class="lede"><?php
        if ($editing && $editing['status'] === 'pending') {
            echo 'This stays waiting for an admin.';
        } elseif ($editing) {
            echo 'Saving keeps this article ' . e((string) $editing['status']) . '.';
        } elseif ($user['role'] === 'admin') {
            echo 'This posts straight to the line.';
        } else {
            echo 'An admin approves this before it goes on the line.';
        }
    ?></p>
    <?php if ($errors !== []): ?>
        <ul class="errors">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="write.php<?= $editing ? '?id=' . (int) $editing['id'] : '' ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            Section
            <select name="topic_id" required>
                <option value="">Choose</option>
                <?php foreach ($topics as $topic): ?>
                    <option value="<?= (int) $topic['id'] ?>"<?= $topicId === (int) $topic['id'] ? ' selected' : '' ?>><?= e((string) $topic['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Title
            <input type="text" name="title" required maxlength="500" value="<?= e($articleTitle) ?>">
        </label>
        <label>
            Image address
            <input type="url" name="image_url" maxlength="2048" placeholder="https://example.com/photo.jpg" value="<?= e($imageUrl) ?>">
        </label>
        <label>
            Or upload an image
            <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp,image/gif">
        </label>
        <label>
            Article
            <textarea name="body" required><?= e($body) ?></textarea>
        </label>
        <button type="submit"><?= $editing ? 'Save changes' : 'Post' ?></button>
    </form>
    <?php if ($mine !== []): ?>
        <section class="block">
            <h2>Waiting</h2>
            <?php foreach ($mine as $item): ?>
                <p class="inline">
                    <span><a href="article.php?id=<?= (int) $item['id'] ?>"><?= e((string) $item['title']) ?></a> · <a href="write.php?id=<?= (int) $item['id'] ?>">Edit</a></span>
                    <span class="meta"><?= e((string) $item['status']) ?></span>
                </p>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
