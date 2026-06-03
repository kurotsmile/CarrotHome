<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pageTitle = 'Home';
require_once APP_PATH . '/views/header.php';
?>

<section class="hero-section">
    <div class="container hero-grid">
        <div class="hero-content">
            <p class="eyebrow">XAMPP + PHP + Cloudflare Tunnel</p>
            <h1>CarrotHome is running from your Windows PC</h1>
            <p class="lead">
                This is a clean PHP starter project for your home web server. Clone it into
                <strong>D:\xampp\htdocs\CarrotHome</strong>, point Apache or Cloudflare Tunnel to it,
                and you have a public-ready landing page.
            </p>
            <div class="actions">
                <a class="btn btn-primary" href="<?= url('status') ?>">Check Server Status</a>
                <a class="btn btn-outline" href="<?= url('about') ?>">About Project</a>
            </div>
        </div>
        <div class="server-panel">
            <h2>Server Snapshot</h2>
            <div class="info-row"><span>App</span><strong><?= e(APP_NAME) ?></strong></div>
            <div class="info-row"><span>Version</span><strong><?= e(APP_VERSION) ?></strong></div>
            <div class="info-row"><span>PHP</span><strong><?= e(PHP_VERSION) ?></strong></div>
            <div class="info-row"><span>Host</span><strong><?= e($_SERVER['HTTP_HOST'] ?? 'localhost') ?></strong></div>
            <div class="info-row"><span>Time</span><strong><?= e(date('Y-m-d H:i:s')) ?></strong></div>
        </div>
    </div>
</section>

<section class="container cards-section">
    <article class="card">
        <h3>Run on XAMPP</h3>
        <p>Designed for a Windows PC using Apache and PHP from XAMPP.</p>
    </article>
    <article class="card">
        <h3>Public by Tunnel</h3>
        <p>Expose your local web server through Cloudflare Tunnel without opening router ports.</p>
    </article>
    <article class="card">
        <h3>Easy to Extend</h3>
        <p>Add MySQL, APIs, login pages, dashboards, or Unity game backend endpoints later.</p>
    </article>
</section>

<?php require_once APP_PATH . '/views/footer.php'; ?>
