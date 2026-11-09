-- 默认欢迎公告，可重复导入。
INSERT INTO `__PREFIX__code2yellow_announcement`
(`title`, `content`, `status`, `mode`, `min_version`, `max_version`, `is_pinned`, `is_urgent`, `revision`, `start_at`, `end_at`, `published_at`, `created_at`, `updated_at`)
SELECT '欢迎使用跨设备传输工具', '欢迎使用跨设备传输工具！

您可以通过主页面的“公告”入口查看使用通知、维护安排和功能说明，未读公告会显示数量提示。

需要升级时，请点击“检查更新”获取最新版本。

感谢您的使用与支持。', 'published', 'all', '', '', 1, 0, 1, 0, 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `__PREFIX__code2yellow_announcement` WHERE `title` = '欢迎使用跨设备传输工具');
