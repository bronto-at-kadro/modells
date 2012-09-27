/*
Navicat MySQL Data Transfer

Source Server         : localhost
Source Server Version : 50519
Source Host           : localhost:3306
Source Database       : dlife

Target Server Type    : MYSQL
Target Server Version : 50519
File Encoding         : 65001

Date: 2012-05-09 05:41:52
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `logs`
-- ----------------------------
DROP TABLE IF EXISTS `logs`;
CREATE TABLE `logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `activity_type` enum('send','open','view','click','conversion','unsubscribe','bounce') NOT NULL,
  `activity_from` date NOT NULL,
  `activity_to` date DEFAULT NULL,
  `activity_last` datetime DEFAULT NULL,
  `activity_total` int(10) unsigned NOT NULL DEFAULT '0',
  `message` varchar(255) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `modified_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `export_started_at` datetime DEFAULT NULL,
  `export_finished_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UX_run` (`activity_type`,`activity_from`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of logs
-- ----------------------------
