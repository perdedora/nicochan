-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 5, 2022 at 07:00 PM
-- Server version: 10.3.34-MariaDB-0ubuntu0.20.04.1
-- PHP Version: 8.0.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `nicochan`
--

-- --------------------------------------------------------

--
-- Table structure for table `antispam`
--

CREATE TABLE IF NOT EXISTS `antispam` (
  `board` varchar(58) NOT NULL,
  `thread` int(11) DEFAULT NULL,
  `hash` char(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created` int(11) NOT NULL,
  `expires` int(11) DEFAULT NULL,
  `passed` smallint(6) DEFAULT 0 NOT NULL,
  `shadow` int(1) DEFAULT 0 NOT NULL,
  `archive` int(1) DEFAULT 0 NOT NULL,
  PRIMARY KEY (`hash`),
  KEY `board` (`board`,`thread`),
  KEY `expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `archive`
--

CREATE TABLE IF NOT EXISTS `archive` (
  `thread_id` int(11) NOT NULL,
  `board` varchar(58) NOT NULL,
  `snippet` text NOT NULL,
  `lifetime` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`board`, `thread_id`),
  KEY `lifetime` (`lifetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `bans`
--

CREATE TABLE IF NOT EXISTS `bans` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ipstart` varbinary(61) NOT NULL,
  `ipend` varchar(61) DEFAULT NULL,
  `cookie` varchar(40) CHARACTER SET ascii NOT NULL,
  `cookiebanned` tinyint(1) DEFAULT 0 NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `expires` int(10) UNSIGNED DEFAULT NULL,
  `board` varchar(58) DEFAULT NULL,
  `creator` int(10) NOT NULL,
  `reason` text,
  `seen` tinyint(1) DEFAULT 0 NOT NULL,
  `post` blob,
  `appealable` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `expires` (`expires`),
  KEY `ipstart` (`ipstart`,`ipend`),
  KEY `cookie` (`cookie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `bans_cookie`
--

CREATE TABLE IF NOT EXISTS `bans_cookie` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cookie` varchar(40) CHARACTER SET ascii NOT NULL,
  `expires` int(11) NOT NULL,
  `creator` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `cookie` (`cookie`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ban_appeals`
--

CREATE TABLE IF NOT EXISTS `ban_appeals` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ban_id` int(10) UNSIGNED NOT NULL,
  `time` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `denied` tinyint(1) DEFAULT 0 NOT NULL,
  `denial_reason` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ban_id` (`ban_id`),
  CONSTRAINT `fk_ban_id` FOREIGN KEY (`ban_id`) REFERENCES `bans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `boards`
--

CREATE TABLE IF NOT EXISTS `boards` (
  `uri` varchar(58) NOT NULL,
  `title` tinytext NOT NULL,
  `subtitle` tinytext,
  PRIMARY KEY (`uri`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `boards`
--

INSERT INTO `boards` (`uri`, `title`, `subtitle`) VALUES
('b', 'Random', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `captchas`
--

CREATE TABLE IF NOT EXISTS `captchas` (
  `cookie` varchar(50) NOT NULL,
  `extra` varchar(200) NOT NULL,
  `text` varchar(255) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`cookie`,`extra`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `cites`
--

CREATE TABLE IF NOT EXISTS `cites` (
  `board` varchar(58) NOT NULL,
  `post` int(11) NOT NULL,
  `target_board` varchar(58) NOT NULL,
  `target` int(11) NOT NULL,
  `shadow` int(1) DEFAULT 0 NOT NULL,
  `archive` int(1) DEFAULT 0 NOT NULL,
  KEY `target` (`target_board`,`target`),
  KEY `post` (`board`,`post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `custom_geoip`
--

CREATE TABLE IF NOT EXISTS `custom_geoip` (
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `country` varchar(6) NOT NULL,
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `filehashes`
--

CREATE TABLE IF NOT EXISTS `filehashes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board` varchar(58) NOT NULL,
  `thread` int(11) NOT NULL,
  `post` int(11) NOT NULL,
  `filehash` text CHARACTER SET ascii NOT NULL,
  `shadow` int(1) DEFAULT 0 NOT NULL,
  `archive` int(1) DEFAULT 0 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `thread_id` (`thread`),
  KEY `post_id` (`post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `flood`
--

CREATE TABLE IF NOT EXISTS `flood` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varchar(61) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `board` varchar(58) NOT NULL,
  `time` int(11) NOT NULL,
  `posthash` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `filehash` char(32) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `isreply` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `posthash` (`posthash`),
  KEY `filehash` (`filehash`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ip_notes`
--

CREATE TABLE IF NOT EXISTS `ip_notes` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `mod` int(11) DEFAULT NULL,
  `time` int(11) NOT NULL,
  `body` text NOT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `ip_lookup` (`ip`,`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `modlogs`
--

CREATE TABLE IF NOT EXISTS `modlogs` (
  `mod` int(11) NOT NULL,
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `board` varchar(58) DEFAULT NULL,
  `time` int(11) NOT NULL,
  `text` text NOT NULL,
  KEY `time` (`time`),
  KEY `mod` (`mod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mods`
--

CREATE TABLE IF NOT EXISTS `mods` (
  `id` smallint(6) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(30) NOT NULL,
  `password` varchar(256) CHARACTER SET ascii NOT NULL COMMENT 'SHA256',
  `version` varchar(64) CHARACTER SET ascii NOT NULL,
  `type` smallint(2) NOT NULL,
  `boards` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`,`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mods`
--

INSERT INTO `mods` (`id`, `username`, `password`, `version`, `type`, `boards`) VALUES
(1, 'admin', '$6$rounds=25000$2GIRIR9OmuI0DU48$87BMSXVc64ElvQyoTGcnIDRfRlyF81NZ2LxxKrKzf4ye7cJTvPbOwZNLv2sBBZzIvGTw.9NjZilc9wSCaA6aV0', '1', 30, '*');

-- --------------------------------------------------------

--
-- Table structure for table `mutes`
--

CREATE TABLE IF NOT EXISTS `mutes` (
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `time` int(11) NOT NULL,
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE IF NOT EXISTS `news` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `time` int(11) NOT NULL,
  `subject` text NOT NULL,
  `body` text NOT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `nicenotices`
--

CREATE TABLE IF NOT EXISTS `nicenotices` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varchar(61) NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `board` varchar(58) DEFAULT NULL,
  `creator` int(10) NOT NULL,
  `reason` text,
  `seen` tinyint(1) DEFAULT 0 NOT NULL,
  `post` blob,
  PRIMARY KEY (`id`),
  KEY `ipstart` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `noticeboard`
--

CREATE TABLE IF NOT EXISTS `noticeboard` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mod` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `subject` text NOT NULL,
  `body` text NOT NULL,
  `reply` int(11) DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE IF NOT EXISTS `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `board` varchar(58) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `content` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_pages` (`name`,`board`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `pms`
--

CREATE TABLE IF NOT EXISTS `pms` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `sender` int(11) NOT NULL,
  `to` int(11) NOT NULL,
  `message` text NOT NULL,
  `time` int(11) NOT NULL,
  `unread` tinyint(1) DEFAULT 1 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `to` (`to`,`unread`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `posts_b`
--

CREATE TABLE IF NOT EXISTS `posts_b` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `email` varchar(30) DEFAULT NULL,
  `name` varchar(35) DEFAULT NULL,
  `trip` varchar(15) DEFAULT NULL,
  `capcode` varchar(50) DEFAULT NULL,
  `body` text NOT NULL,
  `body_nomarkup` text,
  `time` int(11) NOT NULL,
  `bump` int(11) DEFAULT NULL,
  `files` text,
  `num_files` int(11) DEFAULT '0',
  `filehash` text CHARACTER SET ascii,
  `password` varchar(64) DEFAULT NULL,
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `cookie` varchar(40) CHARACTER SET ascii NOT NULL,
  `sticky` int(1) NOT NULL,
  `locked` int(1) NOT NULL,
  `cycle` int(1) NOT NULL,
  `sage` int(1) DEFAULT 0 NOT NULL,
  `hideid` int(1) NOT NULL,
  `shadow` int(1) DEFAULT 0 NOT NULL,
  `archive` int(1) DEFAULT 0 NOT NULL,
  `embed` text,
  `slug` varchar(256) DEFAULT NULL,
  `flag_iso` varchar(6) DEFAULT NULL,
  `flag_ext` varchar(100) DEFAULT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `thread_id` (`thread`,`id`),
  KEY `filehash` (`filehash`(40)),
  KEY `time` (`time`),
  KEY `ip` (`ip`),
  KEY `list_threads` (`thread`,`sticky`,`bump`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `time` int(11) NOT NULL,
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  `board` varchar(58) DEFAULT NULL,
  `post` int(11) NOT NULL,
  `reason` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `robot`
--

CREATE TABLE IF NOT EXISTS `robot` (
  `hash` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA1',
  PRIMARY KEY (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `search_queries`
--

CREATE TABLE IF NOT EXISTS `search_queries` (
  `ip` varchar(61) NOT NULL,
  `time` int(11) NOT NULL,
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `shadow_deleted`
--

CREATE TABLE IF NOT EXISTS `shadow_deleted` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `board` varchar(58) NOT NULL,
  `post_id` int(10) NOT NULL,
  `del_time` int(11) NOT NULL,
  `files` text CHARACTER SET ascii NOT NULL,
  `cite_ids` text CHARACTER SET armscii8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `board` (`board`),
  KEY `post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `theme_settings`
--

CREATE TABLE IF NOT EXISTS `theme_settings` (
  `theme` varchar(40) NOT NULL,
  `name` varchar(40) DEFAULT NULL,
  `value` text,
  KEY `theme` (`theme`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `votes_archive`
--

CREATE TABLE IF NOT EXISTS `votes_archive` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `board` varchar(58) NOT NULL,
  `thread_id` int(10) NOT NULL,
  `ip` varchar(61) CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `ip` (`ip`,`board`,`thread_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `warnings`
--

CREATE TABLE IF NOT EXISTS `warnings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` varchar(61) NOT NULL,
  `created` int(10) UNSIGNED NOT NULL,
  `board` varchar(58) DEFAULT NULL,
  `creator` int(10) NOT NULL,
  `reason` text,
  `seen` tinyint(1) DEFAULT 0 NOT NULL,
  `post` blob,
  PRIMARY KEY (`id`),
  KEY `ipstart` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `hashlist`
--

CREATE TABLE IF NOT EXISTS `hashlist` (
  `id` int NOT NULL AUTO_INCREMENT,
  `hash` binary(64) DEFAULT NULL,
  `reason` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hashfile_pk` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `whitelist_region`
--

CREATE TABLE IF NOT EXISTS `whitelist_region` (
 `id` int(10) AUTO_INCREMENT,
 `ip` varchar(39) NOT NULL,
 `ip_hash` varchar(69) NOT NULL,
  PRIMARY KEY (id, ip),
  UNIQUE KEY `whitelistreg_pk` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `modlogins`
--

CREATE TABLE IF NOT EXISTS `modlogins` (
  `username` varchar(30),
  `ip` varchar(39),
  `ip_hash` varchar(69),
  `time`	int(11),
  `success` boolean NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
COMMIT;

-- --------------------------------------------------------

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
