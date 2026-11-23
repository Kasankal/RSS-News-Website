<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function http_url(?string $url): ?string
{
    $url = trim((string) $url);
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    if ($url === '' || strlen($url) > 2048 || !preg_match('#^https?://#i', $url)) {
        return null;
    }

    return $url;
}

function sharper_image(string $url): string
{
    $larger = preg_replace(
        '#^(https://ichef\.bbci\.co\.uk/(?:ace/standard|news|images/ic)/)\d+/#',
        '${1}1536/',
        $url
    );

    return is_string($larger) ? $larger : $url;
}

function image_src(?string $url): ?string
{
    $url = trim((string) $url);
    $remote = http_url($url);
    if ($remote !== null) {
        return sharper_image($remote);
    }
    if (preg_match('#^uploads/[A-Za-z0-9._-]+$#', $url)) {
        return $url;
    }

    return null;
}

function store_image(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return '';
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $exts = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!is_string($mime) || !isset($exts[$mime])) {
        return '';
    }

    $dir = dirname(__DIR__) . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return '';
    }

    $name = bin2hex(random_bytes(16)) . '.' . $exts[$mime];
    if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
        return '';
    }

    return 'uploads/' . $name;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    $saved = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || !is_string($saved) || !hash_equals($saved, $sent)) {
        http_response_code(403);
        exit('This form expired. Go back and try again.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ?');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }

    return $user;
}

function redirect_if_logged_in(): void
{
    if (current_user() !== null) {
        header('Location: index.php');
        exit;
    }
}

function can_write(array $user): bool
{
    return $user['role'] === 'writer' || $user['role'] === 'admin';
}

function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('You cannot open that page.');
    }

    return $user;
}
