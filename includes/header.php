<?php
$page_title = $page_title ?? 'CarrotHome';
$page_description = $page_description ?? 'Download apps and games';
$style_version = file_exists('styles.css') ? filemtime('styles.css') : time();
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($page_title) ?></title>
<meta name="description" content="<?= h($page_description) ?>">
<link rel="stylesheet" href="styles.css?v=<?= $style_version ?>">
</head>
<body>
<header class="site-header">
<div class="container header-inner">
<div>
<p class="eyebrow">CarrotHome</p>
<h1><a href="index.php" style="color:inherit;text-decoration:none">CarrotHome</a></h1>
<p class="subtitle">App & Game Storage Platform</p>
</div>
</div>
</header>
<main class="container">
