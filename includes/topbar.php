<?php
/** @var array $user */
require_once __DIR__ . '/weather.php';
$weather = thika_weather();
?>
<header class="topbar">
    <a class="mark" href="index.php"><span>RSS</span> Newsline</a>
    <div class="top-meta">
        <span class="top-date"><?= e(date('F j, Y')) ?></span>
        <span class="top-weather"><?= e($weather) ?></span>
        <a class="profile-link" href="profile.php" aria-label="Profile">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <circle cx="12" cy="8" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/>
                <path d="M5.5 19.2c1.4-2.8 3.6-4.2 6.5-4.2s5.1 1.4 6.5 4.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
        </a>
    </div>
    <nav class="nav">
        <a href="index.php">Line</a>
        <?php if (can_write($user)): ?>
            <a href="write.php">Write</a>
        <?php endif; ?>
        <?php if ($user['role'] === 'admin'): ?>
            <a href="admin.php">Admin</a>
        <?php endif; ?>
        <a class="logout" href="logout.php">Log out</a>
    </nav>
</header>
