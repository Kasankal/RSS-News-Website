<?php
/** @var string $title */
/** @var string $mode */
$mode = $mode ?? 'app';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · RSS Newsline</title>
    <link rel="stylesheet" href="assets/css/style.css?v=12">
</head>
<body class="<?= e($mode) ?>">
