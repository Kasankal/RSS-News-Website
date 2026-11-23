CREATE DATABASE IF NOT EXISTS rss_newsline
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE rss_newsline;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('reader', 'writer', 'admin') NOT NULL DEFAULT 'reader',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS topics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS feeds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  url VARCHAR(2048) NOT NULL,
  topic_id INT UNSIGNED NULL,
  fetched_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY feeds_url (url(255)),
  CONSTRAINT feeds_topic FOREIGN KEY (topic_id) REFERENCES topics (id)
);

CREATE TABLE IF NOT EXISTS articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('original', 'sourced') NOT NULL,
  user_id INT UNSIGNED NULL,
  feed_id INT UNSIGNED NULL,
  external_id VARCHAR(512) NULL,
  topic_id INT UNSIGNED NULL,
  title VARCHAR(500) NOT NULL,
  body MEDIUMTEXT NOT NULL,
  url VARCHAR(2048) NULL,
  image_url VARCHAR(2048) NULL,
  status ENUM('pending', 'published', 'rejected') NOT NULL DEFAULT 'published',
  published_at DATETIME NOT NULL,
  hidden_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY sourced_once (feed_id, external_id),
  CONSTRAINT articles_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT articles_feed FOREIGN KEY (feed_id) REFERENCES feeds (id),
  CONSTRAINT articles_topic FOREIGN KEY (topic_id) REFERENCES topics (id)
);

CREATE TABLE IF NOT EXISTS bookmarks (
  user_id INT UNSIGNED NOT NULL,
  article_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, article_id),
  CONSTRAINT bookmarks_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT bookmarks_article FOREIGN KEY (article_id) REFERENCES articles (id)
);

CREATE TABLE IF NOT EXISTS stars (
  user_id INT UNSIGNED NOT NULL,
  article_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, article_id),
  CONSTRAINT stars_user FOREIGN KEY (user_id) REFERENCES users (id),
  CONSTRAINT stars_article FOREIGN KEY (article_id) REFERENCES articles (id)
);
