<?php
$page_title = $page_title ?? 'CarrotHome';
$page_description = $page_description ?? 'Download apps and games';
$style_version = file_exists(__DIR__ . '/../styles.css') ? filemtime(__DIR__ . '/../styles.css') : time();
$header_search = trim($_GET['q'] ?? '');
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_description) ?>">
<meta name="theme-color" content="#ff5900">
<link rel="apple-touch-icon" sizes="180x180" href="favicon/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
<link rel="icon" href="favicon/favicon.ico" sizes="any">
<link rel="manifest" href="favicon/site.webmanifest">
<link rel="preload" href="fonts/mona-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="styles.css?v=<?= $style_version ?>">
</head>
<body>
<header class="site-nav">
  <div class="site-nav__wrapper">
    <a class="site-nav__logo" href="index.php" aria-label="Back to home page">
      <img class="brand-logo-img" src="images/carrot_28.png" alt="CarrotHome">
    </a>
    <form class="header-search" method="get" action="index.php" aria-label="Search apps and games">
      <input name="q" type="search" placeholder="Search apps and games" value="<?= h($header_search) ?>">
      <button class="header-search__button" type="submit" aria-label="Search">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.3-4.3M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </form>
    <nav class="site-nav-main" aria-label="Primary navigation">
      <a href="index.php">Explore</a>
      <a href="index.php?type=app">Applications</a>
      <a href="index.php?type=game">Games</a>
      <a href="index.php?status=publish">New</a>
    </nav>
  </div>
</header>
<main class="container page-shell">
