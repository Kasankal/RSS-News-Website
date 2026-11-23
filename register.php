<?php
declare(strict_types=1);

require __DIR__ . '/includes/auth.php';

redirect_if_logged_in();

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $username = trim((string) ($_POST['username'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors[] = 'Username must be 3–50 characters: letters, numbers, and underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if (strlen($password) > 72) {
        $errors[] = 'Password must be 72 characters or fewer.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if ($errors === []) {
        $check = db()->prepare('SELECT username, email FROM users WHERE username = ? OR email = ?');
        $check->execute([$username, $email]);
        $existing = $check->fetch();
        if ($existing) {
            if (strcasecmp((string) $existing['username'], $username) === 0) {
                $errors[] = 'That username is already taken.';
            }
            if (strcasecmp((string) $existing['email'], $email) === 0) {
                $errors[] = 'That email is already registered.';
            }
        }
    }

    if ($errors === []) {
        $insert = db()->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $insert->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) db()->lastInsertId();
        header('Location: index.php');
        exit;
    }
}

$title = 'Create account';
$mode = 'auth';
require __DIR__ . '/includes/header.php';
?>
<main class="split">
    <section class="brand">
        <p class="mark"><span>RSS</span> Newsline</p>
        <h1>Open your own newsline.</h1>
        <p>One account. The wires you choose, lined up in one place.</p>
    </section>
    <section class="panel">
        <h2>Create account</h2>
        <?php if ($errors !== []): ?>
            <ul class="errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <form method="post" action="register.php" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                Username
                <input type="text" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_]+" value="<?= e($username) ?>" autocomplete="username">
            </label>
            <label>
                Email
                <input type="email" name="email" required maxlength="255" value="<?= e($email) ?>" autocomplete="email">
            </label>
            <label>
                Password
                <input type="password" name="password" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <label>
                Confirm password
                <input type="password" name="confirm" required minlength="8" maxlength="72" autocomplete="new-password">
            </label>
            <button type="submit">Create account</button>
        </form>
        <p class="switch">Already have an account? <a href="login.php">Log in</a></p>
    </section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
