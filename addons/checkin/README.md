# 打卡插件

依赖 `wallet` 和 `third` 插件。微信小程序身份保存在 `fa_third`，`apptype=miniapp`；金币由钱包插件管理。配置页可设置微信小程序 AppID 与 AppSecret，未设置时兼容原站点配置。

接口：`POST /addons/checkin/api/login` 传 `code`；`POST /addons/checkin/api/checkin` 每天一次；`POST /addons/checkin/api/virtuallocation` 扣 2 金币、3 小时内不重复扣。签到奖励由服务端计算：普通工作日 1，周末 2，元旦、劳动节及国庆 1 至 7 日 3。客户端传来的奖励数不参与计算。

旧数据转换：运行 `php addons/checkin/migrate.php` 预览，`php addons/checkin/migrate.php --apply` 写入 `fa_third` 与 `fa_checkin_daily`。脚本保留旧列和旧流水，重复运行不会创建重复绑定或重复签到记录。执行钱包转换后再切换小程序接口。
