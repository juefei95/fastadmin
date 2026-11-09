# 打卡插件

打卡用户：仅新版小程序绑定的会员会加入 `checkin_user`；首次新版微信登录或使用打卡业务时自动建立关系，后台“打卡签到管理 → 打卡用户”可查询。共用会员状态不等于独立的打卡服务封禁状态。已安装的插件请先运行 `php addons/checkin/migrate_checkin_users.php` 预览已有绑定数量，再运行 `php addons/checkin/migrate_checkin_users.php --apply` 创建表并补录，重复执行不会覆盖已有关系。更新插件时需同步后台控制器和视图，重新启用插件补充菜单；加入时间对历史用户使用第三方绑定创建时间。

会员通过后台或会员接口删除后会同步移除打卡用户关系；签到及充值历史不在此处删除。启用更新后的插件以刷新钩子。历史孤立关系可用 `php addons/checkin/cleanup_orphan_users.php` 预览，确认后添加 `--apply` 清理。

充值套餐管理（开发中）：新安装时创建套餐和订单表；已安装的插件可先运行 `php addons/checkin/recharge_schema.php` 预览，再运行 `php addons/checkin/recharge_schema.php --apply` 升级表结构。已有套餐的名称、价格和排序不会被覆盖，已删除套餐不会重新创建。更新插件时需同步 `application/admin` 下的控制器和视图，并启用插件以创建后台权限菜单。套餐支持名称、金币、优惠价、原价、说明、推荐、状态和排序权重，权重越小越靠前；有订单的套餐不能删除，可改为停用。`GET /addons/checkin/api/rechargepackages` 返回已启用的套餐，金额单位为分。

微信 Native 扫码支付：`POST /addons/checkin/api/createrechargeorder` 需要会员 token 和 `package_id`，接受新版或旧版小程序绑定会员，按服务端套餐价格生成订单，调用 epay 的扫码下单并返回 `order_no` 和 `code_url`；`GET /addons/checkin/api/rechargeorder?order_no=...` 仅查询该会员自己的订单和钱包余额。微信回调为 `POST /addons/checkin/payment/wechatnotify`，验签使用 epay，校验订单中的 AppID、商户号和金额，目前仅支持普通商户。需配置与商户号绑定的 AppID、微信 Native 支付权限及公网 HTTPS 回调；服务器须启用 BCMath。下单超时可能已在微信创建订单，本地订单保留待支付，需要后续查单或人工对账。查询接口只查本地状态，不主动向微信查单。真实支付通知、客户端及整个支付流程尚未联调，不能用于收款。

分享与广告奖励：`POST /addons/checkin/api/todayrewards` 返回今天的签到、广告和邀请奖励；`POST /addons/checkin/api/adreward` 发放广告奖励；`POST /addons/checkin/api/claiminvitation` 为首次受邀会员给邀请者发放 5 金币。这些逻辑由 `library/RewardService.php` 统一实现，旧接口插件只调用此服务并适配旧响应。

依赖 `wallet` 和 `third` 插件。微信小程序登录通过微信 `code` 获取 OpenID，以 `fa_third` 中的 `platform=wechat`、`apptype=miniapp` 绑定 FastAdmin 会员；首次登录由 `third` 插件创建会员及绑定，后续登录复用绑定。无需在 `fa_user` 增加 `wechatopenid` 字段。金币由钱包插件管理。配置页可设置微信小程序 AppID 与 AppSecret，未设置时兼容原站点配置。新会员初始金币也可在插件配置页设置，默认 0，仅在新会员注册时发放到钱包并记录流水，不改变已有会员余额。

接口：`POST /addons/checkin/api/login` 传微信 `code`；`POST /addons/checkin/api/accountlogin` 用用户名和密码重新登录；`POST /addons/checkin/api/changeusername`、`POST /addons/checkin/api/changepwdwithoutold` 用于已登录会员设置账号和密码；`POST /addons/checkin/api/checkin` 每天一次；`GET /addons/checkin/api/checkinstatus` 返回当前会员今天的签到奖励和钱包余额，供多设备同步；`GET /addons/checkin/api/rewardconfig` 返回三档签到奖励，供小程序展示；`POST /addons/checkin/api/virtuallocation` 扣 2 金币、3 小时内不重复扣。签到奖励在插件配置页分别设置工作日、周末和指定节日的金币数。指定节日为元旦、农历正月初一至初七、劳动节当天及国庆 1 至 7 日，优先于周末；实际发放由服务端按同一配置计算，客户端传来的奖励数不参与计算。春节日期使用香港天文台公历农历对照表生成的 2026–2100 年离线数据；更新数据可运行 `addons/checkin/tools/generate_spring_festival_dates.ps1`。

仅从旧项目迁移历史数据时才运行 `php addons/checkin/migrate.php` 预览及 `php addons/checkin/migrate.php --apply` 转换。新安装不需要运行迁移脚本。脚本保留旧列和旧流水；没有旧 `wechatopenid` 列时跳过旧微信绑定转换。

Android 公告：新安装自动创建公告表。已安装的站点执行 `php addons/checkin/announcement_schema.php` 预览，再执行 `php addons/checkin/announcement_schema.php --apply`；同步插件内 `application/admin/controller/checkin/Announcement.php` 和 `application/admin/view/checkin/announcement/index.html` 到站点对应目录，然后重新启用插件补充菜单及权限。后台可维护多条公告，排序值越小越优先，同排序取最新一条；时间为北京时间，结束时间为不包含的边界。`GET /addons/checkin/api/announcement` 无需登录，返回当前公告及北京时间日期。保存公告不会重置用户选择，点击“重新向所有设备弹出”才增加版本号。关闭选择保存在设备本地。

Android 首页推广：新安装自动建表。已安装站点运行 `php addons/checkin/promotion_schema.php --apply`，同步插件内 `application/admin/controller/checkin/Promotion.php` 与 `application/admin/view/checkin/promotion/index.html` 到站点对应路径，并重新启用插件补充权限菜单。后台可配置名称、简介、类型、目标、启用状态和排序；未启用时不展示。网页只支持 HTTPS，小程序需使用微信生成的 URL Scheme，应用需填写已安装的 Android 包名。`GET /addons/checkin/api/promotions` 无需登录，最多返回 100 项。
