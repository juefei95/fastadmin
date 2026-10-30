# 打卡插件

依赖 `wallet` 和 `third` 插件。微信小程序登录通过微信 `code` 获取 OpenID，以 `fa_third` 中的 `platform=wechat`、`apptype=miniapp` 绑定 FastAdmin 会员；首次登录由 `third` 插件创建会员及绑定，后续登录复用绑定。无需在 `fa_user` 增加 `wechatopenid` 字段。金币由钱包插件管理。配置页可设置微信小程序 AppID 与 AppSecret，未设置时兼容原站点配置。新会员初始金币也可在插件配置页设置，默认 0，仅在新会员注册时发放到钱包并记录流水，不改变已有会员余额。

接口：`POST /addons/checkin/api/login` 传微信 `code`；`POST /addons/checkin/api/accountlogin` 用用户名和密码重新登录；`POST /addons/checkin/api/changeusername`、`POST /addons/checkin/api/changepwdwithoutold` 用于已登录会员设置账号和密码；`POST /addons/checkin/api/checkin` 每天一次；`GET /addons/checkin/api/rewardconfig` 返回三档签到奖励，供小程序展示；`POST /addons/checkin/api/virtuallocation` 扣 2 金币、3 小时内不重复扣。签到奖励在插件配置页分别设置工作日、周末和指定节日的金币数，默认 1、2、3。指定节日为 1 月 1 日、5 月 1 日、10 月 1 至 7 日，优先于周末；实际发放由服务端按同一配置计算，客户端传来的奖励数不参与计算。

仅从旧项目迁移历史数据时才运行 `php addons/checkin/migrate.php` 预览及 `php addons/checkin/migrate.php --apply` 转换。新安装不需要运行迁移脚本。脚本保留旧列和旧流水；没有旧 `wechatopenid` 列时跳过旧微信绑定转换。
