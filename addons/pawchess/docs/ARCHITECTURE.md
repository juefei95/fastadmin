# 毛球象棋社插件架构说明

## 1. 目标与边界

本插件暂定名称为 `pawchess`，部署在 FastAdmin 项目
`ld-backend-paw-chess` 中，为微信小游戏 `paw-chess-minigame` 提供好友象棋对战服务。

插件负责以下内容：

- 微信小游戏登录和业务访问令牌；
- 创建、加入、准备、恢复、离开和再战好友房；
- 服务端象棋规则校验、回合、超时、和棋和终局裁决；
- Elo 计分、排行榜和可审计的积分流水；
- 后台查看棋手、房间、已结束对局和积分变更。

插件不负责 AI、残局题库、画面、音效或本地练习。这些继续由小游戏本地代码承担。

## 2. 结论：根目录 Node 服务的处理方式

前端项目根目录 `server/` 中的 Node 服务是完整的**参考实现和联调基准**，不应继续作为正式生产服务。它将承担以下过渡职责：

1. 保留其接口测试和行为，作为 PHP 插件的验收基线；
2. 本地开发中可继续启动，供小游戏快速联调；
3. PHP 插件通过契约测试后，将小游戏的 `baseUrl` 切换至 FastAdmin 的 HTTPS 域名；
4. 完成灰度、确认不存在活跃 Node 房间后，停止部署 Node 服务；源码先归档保留，不立即删除。

不建议让 Node 服务与 PHP 插件长期共同处理正式房间：两边各自维护棋盘、令牌和积分，会造成重复结算及状态分叉。

## 3. 总体架构

```text
微信小游戏
  |  HTTPS + Authorization: Bearer <token>
  v
Nginx / HTTPS
  v
FastAdmin + pawchess 插件
  |-- API 控制器：输入验证、鉴权、响应适配
  |-- RoomService：房间状态机、并发锁、超时
  |-- XiangqiRules：合法走法、将军、终局、和棋
  |-- RatingService：Elo、幂等结算、积分流水
  |-- WechatSessionService：code2session、用户绑定
  v
MySQL（事务与行锁）
  |-- fa_user（既有会员账号）
  `-- fa_paw_chess_*（插件自有数据）

定时任务 / 队列
  `-- 处理超时对局、关闭过期等待房、清理过期会话
```

小游戏维持既有的 HTTP 短轮询（默认 1200ms）。第一期不引入 WebSocket；将来若需更低延迟，可在不变更房间状态机和数据表的前提下新增推送网关，HTTP 接口继续保留作重连与兜底。

## 4. 插件目录建议

当前实现采用插件自身的 `appInit` 钩子注册 API 路由，并在 `appDispatch` 钩子中只对本插件的 `api/*` 接口重新开启路由检测，以兼容 FastAdmin 对 `api` 模块默认关闭路由的行为，避免把根级 `application/route.php` 放入插件包并覆盖宿主项目。后台控制器和视图位于插件的 `application/admin/.../pawchess/`，由 FastAdmin 安装器复制到生产目录。维护者可在运行中的项目里手动打包，也可使用 `tools/package.ps1`；若生产位置的后台文件有修改，打包前需核对并同步回插件目录。下面的目录图是目标模块划分，不等同于当前仓库逐文件清单；以插件实际文件和 README 的部署说明为准。

```text
addons/pawchess/
  Pawchess.php                     # 插件安装、卸载、启停钩子
  info.ini                         # 名称、版本、作者、依赖
  install.sql                      # 自有表、索引、后台菜单与权限
  uninstall.sql                    # 仅删除 pawchess 自有对象
  config.php                       # 微信 AppId、计时、Elo、房间保留时间等
  application/
    api/controller/
      Session.php                  # POST /api/session、GET /api/me
      Room.php                     # rooms 的创建、加入、操作、查询
      Ranking.php                  # GET /api/ranking
      Health.php                   # GET /health
    common/service/
      WechatSessionService.php
      RoomService.php
      XiangqiRules.php
      RatingService.php
      RoomSnapshotFactory.php
    common/model/
      Player.php
      Room.php
      RoomMove.php
      Match.php
      RatingLog.php
  application/admin/controller/
    Player.php
    Room.php
    Match.php
    RatingLog.php
  application/admin/model/
  application/admin/view/
  application/admin/lang/zh-cn/
  docs/ARCHITECTURE.md
  tests/
    ContractTest.php
    RulesTest.php
    RoomServiceTest.php
    RatingServiceTest.php
```

所有插件表均使用 `paw_chess_` 前缀（在 FastAdmin 默认前缀下为 `fa_paw_chess_*`），不得复用或改写太喵达人 `game_rank_*` 表。

## 5. 身份与鉴权

### 5.1 微信登录

`POST /api/session` 接收小游戏 `wx.login()` 产生的 `code`。服务端使用配置中的 AppId、AppSecret 请求微信 `code2session`，仅在服务端处理 `openid` 与 `session_key`：

- `session_key` 不落库、不写日志、不返回客户端；
- 调用 `addons/third` 的 `Service::connect('wechat_minigame', ...)`，平台标识为 `wechat_minigame`、类型为 `minigame`，复用 FastAdmin 会员与第三方绑定；
- `paw_chess_player.member_id` 唯一关联 FastAdmin `user.id`，与太喵达人排行榜按会员 ID 关联的方式一致。后续登录只按会员 ID 查找棋手；OpenID 仅用于第三方登录绑定，不由棋类插件保存或哈希持久化；
- 返回第三方登录插件签发的 FastAdmin Token。棋类 API 同时校验 FastAdmin Token 和棋手会话映射；
- 返回当前未结束房间 ID，支持重启后恢复。

开发环境可显式启用 `development_auth`，仅接受随机 `deviceId`。生产环境禁止该开关。

### 5.2 接口权限

- `/health` 与 `POST /api/session` 不需要 Token；
- 其余接口校验 `token` 请求头（兼容 `Authorization: Bearer <token>`）；
- 房间读取和操作必须验证当前用户是红方或蓝方；
- 管理端使用 FastAdmin 后台权限，不能直接暴露给小游戏。

## 6. 数据模型

### 6.1 `paw_chess_player`

一名会员对应一名棋手。

| 字段 | 说明 |
| --- | --- |
| `id` | 主键 |
| `member_id` | 唯一关联 `fa_user.id`；开发设备用户可为空 |
| `name` | 供小游戏展示的棋手名 |
| `points` | 当前 Elo，默认 1000 |
| `games` / `wins` / `draws` | 已计分对局统计 |
| `created_at` | 创建时间（毫秒） |

索引：`UNIQUE(member_id)`、`(points, wins, id)`。

开发环境的设备身份哈希单独保存在 `paw_chess_dev_identity`，通过唯一 `player_id` 映射到棋手。该表只服务于显式开启的开发登录，不参与正式会员登录和排行榜查询。

### 6.2 `paw_chess_room`

保存可恢复的实时对局状态；棋盘和走法快照使用 JSON 文本，避免数据库方言差异。

| 字段 | 说明 |
| --- | --- |
| `id` | 24 位随机十六进制房间 ID |
| `code` | 六位邀请码，活动房间唯一 |
| `status` | `waiting`、`ready`、`active`、`ended`、`closed` |
| `red_player_id` / `blue_player_id` | 双方棋手；蓝方等待时可为空 |
| `red_pet` / `blue_pet` | 客户端头像 ID |
| `board_json` / `moves_json` / `captures_json` | 权威棋局快照 |
| `turn` / `revision` | 当前方与单调递增版本号 |
| `turn_ms` / `deadline_at` | 每步时限与当前回合截止时间 |
| `ready_json` / `rematch_ready_json` | 双方准备与再战确认 |
| `rated` | 是否计分 |
| `series_json` | 本次交手连续对局比分 |
| `result_json` / `rating_json` | 结局与本局积分变动 |
| `started_at` / `ended_at` / `closed_at` | 生命周期时间 |

索引：`UNIQUE(code, status)` 不能正确表达“仅活动房间唯一”，实施时使用活动码独立字段或事务内查重；另建 `(red_player_id, status)`、`(blue_player_id, status)` 和 `(status, deadline_at)` 索引。

### 6.3 `paw_chess_match`

一局完成的不可变审计记录。包含 `room_id`（唯一）、双方棋手、双方 Elo 赛前/赛后值、结果、原因、走法、是否计分、结束时间。`UNIQUE(room_id)` 是防重复结算的核心约束。

### 6.4 `paw_chess_rating_log`

每名棋手每局一条积分流水：`player_id`、`match_id`、`delta`、`before_elo`、`after_elo`、`reason`、`createtime`。索引 `UNIQUE(match_id, player_id)`。

## 7. 房间状态机与并发规则

```text
创建 -> waiting ->（另一人加入）-> ready ->（双方 ready）-> active
  |          |                                      |
  +------ leave/30 分钟过期 -> closed                +-- 终局/认输/超时 -> ended
                                                                    |
                                      双方 rematch -> 新 active 房间（红蓝交换）
```

所有改变房间的操作都在 MySQL 事务中完成，并以 `SELECT ... FOR UPDATE` 锁住房间行：

- `move` 同时检查 `status=active`、操作者座位、`turn` 和客户端 `revision`；
- 成功落子后增加 `revision`，更新 `deadline_at`；
- 每个请求入口和定时任务都会检查超时；
- 结算通过 `paw_chess_match.room_id` 唯一键保证一次且仅一次；
- 排行和积分更新与写入比赛记录在同一事务完成；
- 轮询得到更旧 `revision` 的客户端必须接受服务器快照，不能回写旧棋盘。

## 8. 对外 API 契约

第一期必须兼容小游戏现有 `src/online.js`，因此成功响应直接返回对象，失败响应为 HTTP 4xx/5xx 加 `{ "error": "给玩家看的消息" }`。不要直接返回 FastAdmin 默认的 `{code,msg,data}` 包装，否则现有小游戏无法读取 `token`、`room` 和 `rows`。

| 方法 | 路径 | 请求 | 响应职责 |
| --- | --- | --- | --- |
| GET | `/health` | 无 | 服务可用性 |
| POST | `/api/session` | 正式 `{code}`；开发 `{deviceId}` | Token、棋手、开发标志、活跃房间 |
| GET | `/api/me` | 无 | 当前棋手、活跃房间 |
| POST | `/api/rooms` | `{pet,minutes,rated}` | 建房；创建者执红 |
| POST | `/api/rooms/join` | `{code,pet}` | 加入；加入者执蓝 |
| GET | `/api/rooms/:id` | 无 | 权威房间快照 |
| POST | `/api/rooms/:id/ready` | `{}` | 双方准备后开局 |
| POST | `/api/rooms/:id/move` | `{from,to,revision}` | 校验并执行走棋 |
| POST | `/api/rooms/:id/resign` | `{}` | 认输结算 |
| POST | `/api/rooms/:id/leave` | `{}` | 等待房关闭；进行中视为认输 |
| POST | `/api/rooms/:id/rematch` | `{}` | 双方确认后创建交换阵营的新房 |
| GET | `/api/ranking?page=0` | 页号 | 每页 8 条及本人名次 |

房间快照字段保持当前前端所需：`id`、`code`、`status`、`mySide`、`players`、`board`、`turn`、`moves`、`captures`、`last`、`revision`、`started`、`result`、`rating`、`deadline`、`serverTime`、`turnMs`、`rated`、`ready`、`rematchReady`、`rematchId`、`series`、`endedAt`。

## 9. 象棋规则与结算

`XiangqiRules` 必须是服务端唯一权威：

- 初始局面、每个棋子的合法走法、将军/应将、将死；
- 吃子、轮次、三次重复局面、120 回合无吃子/兵移动或 600 手的休闲判和；
- 忽略客户端伪造的 `special`、胜负、积分和棋盘字段；
- 将 `from`、`to` 映射为当前 90 格棋盘的合法走法后才保存；
- 关键棋标签在服务端根据真实局面计算，可作为展示字段，不参与裁决。

PHP 版本不应依赖小游戏 JavaScript 直接执行。应把 `server/server.cjs` 和 `paw-chess-minigame/src/engine.js` 中的规则逐项移植为 PHP，并将既有 Node 测试的开局、吃子、将军、终局、重复局面制作成共享测试向量。

## 10. Elo 与排行规则

- 初始 Elo：1000；K 值：32；
- 仅 `rated=true` 且双方真实玩家、至少完成 4 个半回合的对局结算 Elo；
- 胜/和/负得分分别为 1 / 0.5 / 0；
- `delta = round(32 * (actual - expected))`；双方增减必须相反；
- 友谊局、AI、残局、过早退出和未满最低步数的对局不改变 Elo；
- 排行只展示至少完成一局计分对局且 `rank_visible=1` 的玩家；
- 排序为 Elo 降序、胜场降序、棋手 ID 升序，保障翻页稳定。

## 11. 安全、运维与可观测性

- 微信 AppSecret 仅存环境变量或服务器密钥配置，不写入插件配置导出、日志或仓库；
- 仅允许 HTTPS；在微信后台配置 request 合法域名；
- 登录和普通 API 分别按 IP + 用户进行限流；
- `code`、Token、openid、session_key 脱敏记录；
- 所有写操作限制请求体大小，验证头像、房间码、时间选项和坐标类型；
- 对房间状态变化、超时结算、积分异常和微信登录失败记录结构化日志；
- 后台只允许查看、关闭异常房间和标记榜单可见性；手工调分需单独审计，不能改写已结算比赛。

## 12. 迁移与上线步骤

1. **冻结契约**：以当前 Node 服务和小游戏测试确认响应字段、错误码、规则和 Elo 数值。
2. **搭建插件骨架**：安装 SQL、配置、后台菜单、模型与 API 路由；不动 `game_rank_*`。
3. **实现核心服务**：先规则与房间事务，再微信登录和 Elo，最后后台页面。
4. **自动验证**：使用共享测试向量；PHP 契约测试对比 Node 服务的同一操作序列及响应含义。
5. **开发环境双跑**：小游戏可分别配置 Node 与 PHP 地址，比较创建、走棋、超时、重连、再战和排行榜结果。
6. **灰度切换**：仅允许新创建房间进入 PHP；Node 的既有活跃房间自然结束。
7. **正式切换**：将小游戏 `baseUrl` 配置改为 FastAdmin HTTPS 域名，监控错误率、房间锁等待和结算流水。
8. **退役 Node**：确认保留窗口内无 Node 活跃房间、积分已核对后停止 Node 进程；保留源码与测试作为历史基准。

## 13. 验收标准

- 小游戏无需改动 `online.js` 即能完成登录、建房、加入、准备、落子、重连、认输、超时、再战与排行；
- 同一 `room_id` 无论重试多少次，最多产生一条比赛记录和双方各一条积分流水；
- 任意伪造走法、越权读房、旧 revision、伪造积分都被拒绝；
- 断线重开后能恢复服务器权威棋盘、阵营、计时和积分；
- 后台能按棋手、时间、房间和结果追溯每一笔结算；
- Node 参考服务与 PHP 插件在共享测试向量上行为一致。
