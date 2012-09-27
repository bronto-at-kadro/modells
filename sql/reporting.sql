/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50519
Source Host           : localhost:3306
Source Database       : dlife

Target Server Type    : MYSQL
Target Server Version : 50519
File Encoding         : 65001

Date: 2012-04-12 01:59:01
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `reporting`
-- ----------------------------
DROP TABLE IF EXISTS `reporting`;
CREATE TABLE `reporting` (
  `contact_id` char(36) NOT NULL,
  `contact_email` varchar(128) DEFAULT NULL,
  `message_id` char(36) NOT NULL,
  `message_name` varchar(128) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `bounced_at` datetime DEFAULT NULL,
  `opened_at` datetime DEFAULT NULL,
  `opened_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `clicked_at` datetime DEFAULT NULL,
  `clicked_count` tinyint(2) unsigned NOT NULL DEFAULT '0',
  `added_at` datetime NOT NULL,
  `modified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`contact_id`,`message_id`),
  KEY `IX_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of reporting
-- ----------------------------
