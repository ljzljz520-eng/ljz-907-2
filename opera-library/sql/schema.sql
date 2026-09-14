-- ============================================================
-- 地方戏曲影像库 数据库结构 + 初始数据
-- 使用方式: mysql -u root -p < sql/schema.sql
-- ============================================================
CREATE DATABASE IF NOT EXISTS opera_library
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE opera_library;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS play_images;
DROP TABLE IF EXISTS plays;
DROP TABLE IF EXISTS opera_types;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS = 1;

-- 管理员表
CREATE TABLE admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE COMMENT '登录名',
  password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='后台管理员';

-- 剧种表
CREATE TABLE opera_types (
  id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name   VARCHAR(100) NOT NULL UNIQUE COMMENT '剧种名称，如：昆曲、越剧',
  region VARCHAR(100) NOT NULL DEFAULT '' COMMENT '主要流传地区'
) ENGINE=InnoDB COMMENT='戏曲剧种';

-- 剧目表
CREATE TABLE plays (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(200) NOT NULL COMMENT '剧目名称',
  type_id    INT UNSIGNED NOT NULL COMMENT '剧种ID',
  region     VARCHAR(100) NOT NULL DEFAULT '' COMMENT '地区',
  inheritor  VARCHAR(100) NOT NULL DEFAULT '' COMMENT '代表性传承人',
  actors     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '主要演员',
  era        VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '录制年代，如：1980年代',
  video_url  VARCHAR(500) NOT NULL DEFAULT '' COMMENT '视频链接',
  cover      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '封面剧照',
  description TEXT COMMENT '剧目简介',
  views      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览次数',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_type (type_id),
  KEY idx_region (region),
  KEY idx_inheritor (inheritor),
  CONSTRAINT fk_plays_type FOREIGN KEY (type_id) REFERENCES opera_types(id)
) ENGINE=InnoDB COMMENT='剧目';

-- 剧照表（一个剧目多张剧照）
CREATE TABLE play_images (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  play_id    INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL COMMENT '图片相对路径',
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_images_play FOREIGN KEY (play_id) REFERENCES plays(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='剧目剧照';

-- 初始管理员：admin / admin123（部署后请立即修改）
INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$10$ansY.UYzexIbeV6XEg3QU.wHFMfLSQQScmBi0TduoVgVE/WNwSpNy');

-- 初始剧种
INSERT INTO opera_types (name, region) VALUES
('昆曲',   '江苏'),
('京剧',   '北京'),
('越剧',   '浙江'),
('黄梅戏', '安徽'),
('豫剧',   '河南'),
('川剧',   '四川'),
('粤剧',   '广东'),
('评剧',   '河北'),
('秦腔',   '陕西'),
('花鼓戏', '湖南');
