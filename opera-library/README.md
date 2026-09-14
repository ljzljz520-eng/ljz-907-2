# 地方戏曲影像库

地方戏曲（非物质文化遗产）数字影像资料平台。后台维护剧目资料并支持 CSV 批量导入，前台按剧种、地区、传承人筛选浏览，查看剧目详情与相关剧目。

## 功能

**前台**
- 剧目列表：按 **剧种 / 地区 / 传承人** 筛选，支持剧目名与演员关键词搜索，分页展示
- 剧目详情：基本信息、在线视频（mp4 直链 / B站 / YouTube 自动嵌入）、剧照画廊、浏览计数
- 相关剧目：同剧种优先、其次同地区自动推荐

**后台**（`/admin/`，默认账号 `admin` / `admin123`，部署后请立即修改）
- 控制台：收录统计与最新剧目
- 剧目管理：分页列表、搜索、新增 / 编辑 / 删除，封面与多剧照上传
- CSV 导入：批量导入剧目，自动建剧种，同名同剧种执行更新，事务导入、逐行错误报告，兼容 UTF-8 / GBK 编码
- 剧种管理：增删剧种（有剧目的剧种禁止删除）

## 技术栈

PHP 8 + MySQL（PDO 预处理语句）/ 原生 CSS，无框架依赖。

## 目录结构

```
opera-library/
├── config/config.php      # 数据库与站点配置（支持环境变量覆盖）
├── includes/              # 引导、PDO 连接、公共函数、前台布局
├── admin/                 # 后台：登录、剧目、导入、剧种管理
├── assets/                # CSS 与占位图
├── uploads/               # 上传的剧照（已禁止脚本执行）
├── sql/schema.sql         # 建库建表 + 初始数据
├── sample_data/           # 示例 CSV（18 条经典剧目）
├── tools/seed.php         # 命令行种子脚本（导入示例数据并关联剧照）
├── index.php              # 前台列表
└── play.php               # 剧目详情
```

## 部署

1. **建库**（会创建 `opera_library` 库、数据表、初始管理员与剧种）：
   ```bash
   mysql -u root -p < sql/schema.sql
   ```
2. **配置数据库**：编辑 `config/config.php`，或用环境变量 `DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS`。
3. **目录权限**：确保 `uploads/` 可被 PHP 写入。
4. **Web 服务**：将站点根目录指向 `opera-library/`。
   - 生产：Nginx/Apache + PHP-FPM
   - 本地开发：`php -S 127.0.0.1:8000 -t opera-library`
5. **（可选）导入示例数据**：后台「CSV 导入」上传 `sample_data/plays_sample.csv`，或命令行执行 `php tools/seed.php`。

## CSV 格式

首行表头可省略，列顺序：`剧目名称*, 剧种*, 地区, 传承人, 演员, 年代, 视频链接, 简介`

- 剧种不存在时自动创建
- 「剧目名称 + 剧种」相同视为同一剧目，重复导入执行更新
- 整个文件在一个事务中导入，出错自动回滚

## 安全说明

- 全部 SQL 使用 PDO 预处理语句；输出统一 `htmlspecialchars` 转义
- 后台表单均带 CSRF 令牌；密码 `password_hash` 存储；登录失败 5 次锁定 10 分钟
- 上传校验扩展名 + MIME，随机文件名存储，`uploads/` 禁止脚本执行
