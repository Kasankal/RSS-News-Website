<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$identity = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($identity === '' || $password === '') {
        $errors[] = 'Enter your username or email and your password.';
    } else {
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identity, strtolower($identity)]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            $errors[] = 'Those details do not match an account.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['id'];
            header('Location: index.php');
            exit;
        }
    }
}

$title = 'Log in';
$mode = 'auth';
require __DIR__ . '/includes/header.php';
?>
<main class="split">
    <section class="brand">
        <p class="mark"><span>RSS</span> Newsline</p>
        <h1>Back to the line.</h1>
        <p>Sign in to pick up the feeds you follow.</p>
    </section>
    <section class="panel">
        <h2>Log in</h2>
        <?php if ($errors !== []): ?>
            <ul class="errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="login.php" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                Username or email
                <input type="text" name="identity" required maxlength="255" value="<?= e($identity) ?>" autocomplete="username">
            </label>
            <label>
                Password
                <input type="password" name="password" required maxlength="72" autocomplete="current-password">
            </label>
            <button type="submit">Log in</button>
        </form>
        <p class="switch">New here? <a href="register.php">Create an account</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
