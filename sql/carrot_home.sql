CREATE DATABASE IF NOT EXISTS carrot_home CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE carrot_home;

CREATE TABLE IF NOT EXISTS apps (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  app_id VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL,
  name_en VARCHAR(255) NOT NULL,
  type VARCHAR(80) DEFAULT 'app',
  status ENUM('publish','draft','trash') DEFAULT 'publish',
  priority INT DEFAULT 0,
  date_create DATETIME DEFAULT CURRENT_TIMESTAMP,

  icon TEXT NULL,
  images JSON NULL,
  store_links JSON NULL,
  download_links JSON NULL,
  video_links JSON NULL,
  category JSON NULL,
  icons JSON NULL,

  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_apps_app_id (app_id),
  UNIQUE KEY uq_apps_slug (slug),
  KEY idx_apps_status (status),
  KEY idx_apps_type (type),
  KEY idx_apps_priority (priority),
  KEY idx_apps_date_create (date_create),
  FULLTEXT KEY ft_apps_name_appid (name_en, app_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO apps (
  app_id,
  slug,
  name_en,
  type,
  status,
  priority,
  date_create,
  icon,
  images,
  store_links,
  download_links,
  video_links,
  category,
  icons
) VALUES (
  '4 checkers',
  '4-checkers',
  '4 checkers',
  'game',
  'publish',
  0,
  '2023-10-08 23:26:04',
  'https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fjpeg%2F1688479675766_icon.jpg?alt=media&token=77f92365-0d3b-4924-820a-4e1a8c8caaac',
  JSON_OBJECT(
    'img1','https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fpng%2F1688479687893_f6462c8b53f7ca2a4839f4ea67d76783_1.png?alt=media&token=1cc4b7ac-f85b-4946-ab0b-473bcf79aacb',
    'img2','https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fpng%2F1688479692464_a.png?alt=media&token=fbc67848-82a3-41fd-b455-c71d6b1c1c34',
    'img3','https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fpng%2F1688479699314_f6462c8b53f7ca2a4839f4ea67d76783_4.png?alt=media&token=fd99b7ca-6388-47c5-b250-eb2865bcb765',
    'img4','https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fpng%2F1688479705642_f6462c8b53f7ca2a4839f4ea67d76783_6.png?alt=media&token=fa0a2e3a-b4ab-4b30-9bda-860c82bcd73f',
    'img5','https://firebasestorage.googleapis.com/v0/b/carrotstore.appspot.com/o/app%2Fimage%2Fpng%2F1688479712063_f6462c8b53f7ca2a4839f4ea67d76783_8.png?alt=media&token=25c5b4d8-9d0a-4995-bc41-f8e9543a32c3'
  ),
  JSON_OBJECT(
    'google_play','https://play.google.com/store/apps/details?id=com.carrotstore.checkers',
    'amazon_app_store','https://www.amazon.com/Carrot-4-checkers/dp/B0BXLYR36M/',
    'microsoft_store','https://www.microsoft.com/p/4-checkers/9NCDVSRT9XT8',
    'itch','https://carrotstore.itch.io/4checkers',
    'uptodown','https://4-checkers.uptodown.com/android',
    'huawei_store','',
    'simmer',''
  ),
  JSON_OBJECT(
    'apk_file','https://drive.google.com/uc?export=download&id=1mQ7Ui-aEYfroqBZ5W4fahRV5HSg6ZgFW',
    'exe_file','https://drive.google.com/uc?export=download&id=1iGVeJWsIX5O7bBw0JlzunTksELcaide1',
    'deb_file','https://drive.google.com/uc?export=download&id=1-G6Ea-GooDoIPeOjAjiRuXXifSW2xi6v',
    'dmg_file','https://drive.google.com/uc?export=download&id=1eoYl0BzgyoW98w0FwU1TA1EzM2ffo8gd',
    'ipa_file',''
  ),
  JSON_OBJECT(
    'youtube_link','https://youtu.be/9lYK36PWkgQ'
  ),
  JSON_ARRAY(),
  JSON_OBJECT(
    'icon_amazon','https://m.media-amazon.com/images/I/81ooLsi8ZDL.png',
    'icon_gg','https://play-lh.googleusercontent.com/qmsL-fGj2YhQjzSui6jLCHVq-gio-i7iZsc6qrg95RgEBIl47fo9pO7spX9-a5rO3Kg=s512-rw',
    'icon_itch','https://img.itch.zone/aW1nLzExNTA5MDMxLnBuZw==/315x250%23c/WcUkej.png',
    'icon_ms','https://store-images.s-microsoft.com/image/apps.26717.13565461610534697.dca2abd8-e20c-4661-b392-bc541508c0f1.e2e94e54-bb02-4f61-a167-1e5e9250e698?w=207'
  )
);

-- List app publish
SELECT id, app_id, slug, name_en, type, status, priority, date_create, icon
FROM apps
WHERE status = 'publish'
ORDER BY priority DESC, date_create DESC;

-- Detail app by slug
SELECT *
FROM apps
WHERE slug = '4-checkers'
LIMIT 1;

-- Search app
SELECT id, app_id, slug, name_en, type, status, priority, date_create, icon
FROM apps
WHERE status = 'publish'
  AND (name_en LIKE '%checkers%' OR app_id LIKE '%checkers%' OR type LIKE '%checkers%')
ORDER BY priority DESC, date_create DESC;
