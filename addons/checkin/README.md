# 打卡插件

依赖 `wallet` 和 `third` 插件。微信小程序登录通过微信 `code` 获取 OpenID，以 `fa_third` 中的 `platform=wechat`、`apptype=miniapp` 绑定 FastAdmin 会员；首次登录由 `third` 插件创建会员及绑定，后续登录复用绑定。无需在 `fa_user` 增加 `wechatopenid` 字段。金币由钱包插件管理。配置页可设置微信小程序 AppID 与 AppSecret，未设置时兼容原站点配置。

接口：`POST /addons/checkin/api/login` 传 `code`；`POST /addons/checkin/api/checkin` 每天一次；`POST /addons/checkin/api/virtuallocation` 扣 2 金币、3 小时内不重复扣。签到奖励由服务端计算：普通工作日 1，周末 2，元旦、劳动节及国庆 1 至 7 日 3。客户端传来的奖励数不参与计算。

仅从旧项目迁移历史数据时才运行 `php addons/checkin/migrate.php` 预览及 `php addons/checkin/migrate.php --apply` 转换。新安装不需要运行迁移脚本。脚本保留旧列和旧流水；没有旧 `wechatopenid` 列时跳过旧微信绑定转换。
