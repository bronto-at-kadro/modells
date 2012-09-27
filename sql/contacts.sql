/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50519
Source Host           : localhost:3306
Source Database       : vibrant

Target Server Type    : MYSQL
Target Server Version : 50519
File Encoding         : 65001

Date: 2012-05-09 06:47:33
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `contacts`
-- ----------------------------
DROP TABLE IF EXISTS `contacts`;
CREATE TABLE `contacts` (
  `contact_id` binary(20) NOT NULL,
  `customer_number` varchar(255) DEFAULT NULL,
  `contact_email` varchar(128) NOT NULL,
  `status` enum('active','onboarding','transactional','bounce','unconfirmed','unsub') NOT NULL,
  `msg_pref` enum('text','html') NOT NULL,
  `source` enum('manual','import','api','webform','sforcereport') NOT NULL,
  `custom_source` enum('text','html') DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `soft_bounce_count` int(10) unsigned NOT NULL DEFAULT '0',
  `soft_bounce_last_date` datetime DEFAULT NULL,
  `hard_bounce_count` int(10) unsigned NOT NULL DEFAULT '0',
  `hard_bounce_last_date` datetime DEFAULT NULL,
  `valid` enum('Y','N') NOT NULL DEFAULT 'Y',
  PRIMARY KEY (`contact_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Created 2012-05-09 10:43:39';

-- ----------------------------
-- Records of contacts
-- ----------------------------
