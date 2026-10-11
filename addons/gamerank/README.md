# 游戏排行榜

本插件依赖已启用的 `third` 插件。它使用 `fa_third` 保存微信小游戏 `openid` 与 FastAdmin 会员的绑定关系，自己的三张表分别保存玩家、曲目成绩和积分流水：

- `fa_game_rank_player`：昵称、头像、累计积分和榜单展示状态；
- `fa_game_rank_record`：每位玩家在每个“游戏 + 曲目 + 难度”组合上的最佳成绩、准确率和连击；
- `fa_game_rank_point_log`：每一局积分结算的不可重复流水。

## 配置与安装

1. 安装并启用 `third` 插件。
2. 安装并启用 `gamerank` 插件：`php think addon -a=gamerank -c=install`，随后执行 `php think addon -a=gamerank -c=enable`。
3. 正式环境在插件设置的“微信小游戏”中填写小游戏 AppID 和 AppSecret。
4. 在微信公众平台把后端域名配置为合法请求域名。

开发环境（`app_debug=true`）会优先读取 `addons/gamerank/config.local.php`；该文件已被 `.gitignore` 忽略，适合保存本机的 AppID 和 AppSecret。生产环境不会读取它，仍以后台保存的 `config.php` 配置为准。调试环境在后台保存“游戏排行榜”配置时会写入 `config.local.php`，不会改动正式配置；该本地文件同时兼容与 `config.php` 相同的完整配置数组及后台生成的 `name => value` 简化数组。

插件启用时会把插件内的 `application/admin/` 和 `public/assets/` 文件复制到主应用，因此后台侧栏会出现“游戏排行榜”，其中包含“游戏玩家”、“曲目成绩”和“积分流水”三个只读管理页面。插件源代码保留在 `addons/gamerank/`，不要手工复制这些文件。

## API

所有接口均为 `/addons/gamerank/api/<action>`，响应沿用 FastAdmin 的 `{code,msg,time,data}` 格式。

| 方法 | 接口 | 说明 |
| --- | --- | --- |
| POST | `login` | 传入 `wx.login()` 得到的 `code`，返回 FastAdmin token 和玩家资料。 |
| GET | `leaderboard` | 公开榜；`type=global` 为累计积分榜，`type=chart` 需传 `game_code`、`song_id`、`difficulty`，按最佳成绩排行。 |
| GET | `me` | 返回当前登录玩家。 |
| POST | `profile` | 更新 `nickname`、`avatar_url`、`rank_visible`。 |
| POST | `settle` | 传入 `round_id`、`points`、`game_code`、`song_id`、`difficulty`、`score`、`accuracy`（万分比）、`max_combo`，结算积分并更新单曲最佳成绩。 |

小游戏在登录后将 `data.token` 放入后续请求的 `token` Header。

`settle` 通过 `round_id` 做幂等，网络重试不会重复加分。它为基础接入提供了单局积分上限；客户端数据仍然不可作为正式竞技反作弊依据，正式竞技榜应在服务端校验提交的成绩或回放数据后再调用积分结算服务。
