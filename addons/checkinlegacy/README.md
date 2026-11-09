# 旧版打卡小程序兼容插件

安装前先启用 `third`、`wallet` 插件。安装本插件后，在配置页填写**旧小程序**的 AppID 和 AppSecret；新小程序继续使用 `checkin` 插件自己的配置。不要把两个小程序的凭据填反。

插件通过 `app_init` 和 `app_dispatch` 钩子，仅对旧小程序使用的 `/api/user/wechatregister`、`/api/user/checkin`、`/api/user/todayrewards`、`/api/user/claiminvitation`、`/api/user/adreward`、`/api/user/virtuallocation`、`/api/user/login`、`/api/user/index`、`/api/user/changeusername`、`/api/user/changepwdwithoutold` 开启并注册路由，无需修改主程序 `User.php`。FastAdmin 在启用或停用插件时会刷新生成的 `application/extra/addons.php` 钩子配置。微信登录使用 `fa_third` 的 `platform=wechat_legacy`，与新小程序的 `platform=wechat` 分开；首次登录会创建一条 FastAdmin 会员及钱包记录。无需修改 `fa_user` 的字段。旧接口的 `score` 映射为钱包金币，签到记录写入本插件的 `legacy_checkin_daily` 表。

安装后请清理 FastAdmin 插件和路由缓存，再用旧小程序的全新 `wx.login` code 验证 `/api/user/wechatregister`。签到、今日奖励、广告、邀请和虚拟定位的业务逻辑统一由 `checkin` 插件的 `RewardService` 实现；本插件只处理旧小程序身份、旧参数与响应格式。旧接口仍接受 `addscorenum`，签到和广告每次限定为 1 至 10 金币；请按旧小程序实际奖励规则调整该限制。邀请码每个受邀会员只发放一次。

本插件不会自动迁移旧站会员、积分和签到历史。若旧小程序继续请求旧站域名，还需将该域名的请求转发到安装本插件的新站，或修改小程序请求域名。

旧站数据已导入当前数据库时，可先备份数据库并暂停业务写入，在 PHP 容器运行 `php addons/checkinlegacy/migrate.php` 只读预览，确认数据和目标表后再运行 `php addons/checkinlegacy/migrate.php --apply`。该脚本要求 `third`、`wallet_account`、`wallet_ledger`、`legacy_checkin_daily` 均为空，不会删除或合并现有记录；表不存在或数据异常时停止。脚本按北京时间生成签到日期，将积分余额、流水、旧微信绑定及邀请防重信息写入插件表，保留原会员及积分流水。全部写入在一个事务中完成；成功后再次执行会因目标表非空而停止，不会重复导入。请先安装所需插件并单独处理不再需要的旧插件数据，切勿在业务写入期间运行。

后台“旧版打卡签到管理 → 旧版打卡用户”展示第三方登录中 `platform=wechat_legacy`、`apptype=miniapp` 的绑定记录及其关联会员，不会创建或迁移会员。已安装的插件更新文件后，停用再启用一次插件以补建菜单，无需卸载重装；刷新后台页面后即可查看。
