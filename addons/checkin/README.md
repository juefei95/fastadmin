# 打卡插件

打卡用户：仅新版小程序绑定的会员会加入 `checkin_user`；首次新版微信登录或使用打卡业务时自动建立关系，后台“打卡管理 → 打卡用户”可查询。共用会员状态不等于独立的打卡服务封禁状态。已安装的插件请先运行 `php addons/checkin/migrate_checkin_users.php` 预览已有绑定数量，再运行 `php addons/checkin/migrate_checkin_users.php --apply` 创建表并补录，重复执行不会覆盖已有关系。更新插件时需同步后台控制器和视图，重新启用插件补充菜单；加入时间对历史用户使用第三方绑定创建时间。

充值套餐管理（开发中）：新安装时创建套餐和订单表；已安装的插件可先运行 `php addons/checkin/recharge_schema.php` 预览，再运行 `php addons/checkin/recharge_schema.php --apply` 升级表结构。已有套餐的名称、价格和排序不会被覆盖，已删除套餐不会重新创建。更新插件时需同步 `application/admin` 下的控制器和视图，并启用插件以创建后台权限菜单。套餐支持名称、金币、优惠价、原价、说明、推荐、状态和排序权重，权重越小越靠前；有订单的套餐不能删除，可改为停用。`GET /addons/checkin/api/rechargepackages` 返回已启用的套餐，金额单位为分。订单创建、支付回调和金币入账尚未实现，不能用于收款。

分享与广告奖励：`POST /addons/checkin/api/todayrewards` 返回今天的签到、广告和邀请奖励；`POST /addons/checkin/api/adreward` 发放广告奖励；`POST /addons/checkin/api/claiminvitation` 为首次受邀会员给邀请者发放 5 金币。这些逻辑由 `library/RewardService.php` 统一实现，旧接口插件只调用此服务并适配旧响应。

依赖 `wallet` 和 `third` 插件。微信小程序登录通过微信 `code` 获取 OpenID，以 `fa_third` 中的 `platform=wechat`、`apptype=miniapp` 绑定 FastAdmin 会员；首次登录由 `third` 插件创建会员及绑定，后续登录复用绑定。无需在 `fa_user` 增加 `wechatopenid` 字段。金币由钱包插件管理。配置页可设置微信小程序 AppID 与 AppSecret，未设置时兼容原站点配置。新会员初始金币也可在插件配置页设置，默认 0，仅在新会员注册时发放到钱包并记录流水，不改变已有会员余额。

接口：`POST /addons/checkin/api/login` 传微信 `code`；`POST /addons/checkin/api/accountlogin` 用用户名和密码重新登录；`POST /addons/checkin/api/changeusername`、`POST /addons/checkin/api/changepwdwithoutold` 用于已登录会员设置账号和密码；`POST /addons/checkin/api/checkin` 每天一次；`GET /addons/checkin/api/checkinstatus` 返回当前会员今天的签到奖励和钱包余额，供多设备同步；`GET /addons/checkin/api/rewardconfig` 返回三档签到奖励，供小程序展示；`POST /addons/checkin/api/virtuallocation` 扣 2 金币、3 小时内不重复扣。签到奖励在插件配置页分别设置工作日、周末和指定节日的金币数。指定节日为元旦、农历正月初一至初七、劳动节当天及国庆 1 至 7 日，优先于周末；实际发放由服务端按同一配置计算，客户端传来的奖励数不参与计算。春节日期使用香港天文台公历农历对照表生成的 2026–2100 年离线数据；更新数据可运行 `addons/checkin/tools/generate_spring_festival_dates.ps1`。

仅从旧项目迁移历史数据时才运行 `php addons/checkin/migrate.php` 预览及 `php addons/checkin/migrate.php --apply` 转换。新安装不需要运行迁移脚本。脚本保留旧列和旧流水；没有旧 `wechatopenid` 列时跳过旧微信绑定转换。
