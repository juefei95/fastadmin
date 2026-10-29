# 通用钱包插件

钱包插件只负责账户、币种、余额与流水，不包含打卡或微信业务。余额为整数最小单位，当前币种 `coin` 表示金币。以后如需购买，可使用 `type=purchase`，并传入订单的 `source_type` 和 `source_id` 防止重复记账。

业务代码在 `Db::transaction()` 内调用 `\addons\wallet\library\Wallet::change($userId, $amount, $type, $memo, $currency, $sourceType, $sourceId)`。正数入账，负数扣费；余额不足会抛异常。交易记录和业务记录应在同一事务内提交。用户可通过 `/addons/wallet/api/balance` 与 `/addons/wallet/api/ledger` 查看自己的余额和流水，接口需要 FastAdmin 用户 token。

后台主菜单“钱包管理”下有“会员钱包”和“钱包流水”两个子菜单。会员钱包可按用户 ID 增减金币并填写原因，调整会写入 `admin_adjust` 流水。钱包流水可按会员、币种、类型筛选并分页。钱包插件安装时会创建 `wallet_account` 和 `wallet_ledger` 表。

会员新增时，插件通过注册钩子和会员模型新增事件创建零余额金币账户；会员删除时，通过删除钩子和模型删除事件清理该会员的钱包账户及流水。重复触发是幂等的。插件必须处于启用状态，钩子配置须随插件启用刷新。

从旧版本转换：先备份数据库，在 PHP 容器中运行 `php addons/wallet/migrate.php` 预览，再运行 `php addons/wallet/migrate.php --apply`。脚本将 `fa_user.score` 和 `fa_user_score_log` 复制到钱包表，不删除旧数据；再次运行不会重复复制。只迁移仍存在的会员，已删除会员的孤儿流水保留在旧表。

打卡插件依赖本插件和第三方登录插件；它的转换脚本另见 `addons/checkin/README.md`。
