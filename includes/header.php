<?php
$page_title = $page_title ?? 'CarrotHome';
$page_description = $page_description ?? 'Download apps and games';
$style_version = file_exists(__DIR__ . '/../styles.css') ? filemtime(__DIR__ . '/../styles.css') : time();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_description) ?>">
<link rel="preload" href="fonts/mona-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="styles.css?v=<?= $style_version ?>">
</head>
<body>
<header class="site-nav">
  <div class="site-nav__wrapper">
    <a class="site-nav__logo" href="index.php" aria-label="Back to home page">
      <span class="logo-mark">C</span>
      <span>CarrotHome</span>
    </a>
    <nav class="site-nav-main" aria-label="Primary navigation">
      <a href="index.php">Explore</a>
      <a href="index.php?type=app">Applications</a>
      <a href="index.php?type=game">Games</a>
      <a href="index.php?status=publish">New</a>
    </nav>
  </div>
</header>
<main class="container page-shell">
