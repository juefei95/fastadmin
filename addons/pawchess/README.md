# 毛球象棋社后端插件

本插件对接 `paw-chess-minigame/src/online.js` 的 HTTP 协议。架构和接口详见 [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)。

## 部署

1. 使用 FastAdmin 插件安装器安装本插件；安装器执行 `install.sql` 并替换 `__PREFIX__`。插件的 `appInit` 钩子注册小游戏路由，`appDispatch` 钩子只对本插件的 `api/*` 接口重新开启路由检测（FastAdmin 默认对 `api` 模块关闭路由）。
2. 已安装旧版的环境升级时，插件升级钩子会幂等补齐 `paw_chess_player.member_id`、建立独立开发身份表，并从棋手表移除 `identity_hash`；棋手积分、对局与会员关联会保留。通过插件管理器执行升级，不要卸载重装。
3. 在插件管理中点击“毛球象棋社”对应的配置入口，填写微信小游戏 AppID 和 AppSecret；也可通过 `WECHAT_APP_ID`、`WECHAT_APP_SECRET` 环境变量提供配置。微信登录经 `third` 插件的 `wechat_minigame` 绑定复用 FastAdmin 会员。必须由 HTTPS 反向代理提供小游戏域名。不要把 AppSecret 写入仓库。
4. 开发环境需要设备身份登录时，设置 `PAW_CHESS_DEV_AUTH=1`，并保证 `APP_ENV` 不是 `production`。正式环境关闭此变量。
5. 将微信小游戏 `src/network-config.js` 的 `baseUrl` 指向此后端 HTTPS 根地址，且在微信平台配置 request 合法域名。
6. 后台只读审计入口为 `/admin/pawchess/overview/players`、`rooms`、`matches`、`ratinglog`，插件安装时创建相应菜单与权限规则。

## 删除会员与彻底重置

FastAdmin 删除会员时，插件的 `userDeleteSuccessed` 钩子会在会员删除事务中删除该会员对应的棋手、登录会话、参与的房间、对局记录及积分流水；任一清理失败会使会员删除事务回滚。只解绑 `third` 的某个登录方式不等同于删除会员，因此不会删除其棋手资料，这与太喵达人排行榜按会员生命周期清理的做法一致。

如需彻底清空毛球象棋社的旧数据并重建全新表结构，先备份数据库，再通过插件管理卸载并重新安装 `pawchess`。卸载脚本会删除所有 `paw_chess_*` 棋类业务表（包括独立开发身份映射）；不会删除 FastAdmin 的会员表或 `third` 第三方绑定数据。该操作不可恢复原棋手积分和历史对局。

## 打包

插件目录是发布源码。可在运行中的项目里手动使用 FastAdmin 插件管理功能打包，也可从项目根目录运行 `./addons/pawchess/tools/package.ps1`。脚本通过 `docker exec ld-backend-paw-chess-debug-web php ...` 在 Web 容器内检查 PHP 语法并调用 FastAdmin 打包命令，因此需要先启动 Web 容器以及其依赖的 MySQL 服务。若修改了运行位置的 `application/admin/.../pawchess/` 文件，运行 `./addons/pawchess/tools/package.ps1 -SyncInstalledFiles` 会先复制到插件目录再打包；核心 API、规则和文档直接保存在插件目录。

本开发仓库同时保留运行目录 `application/admin/controller/pawchess/` 与 `application/admin/view/pawchess/` 的副本，供当前检出目录直接加载；`application/extra/addons.php` 注册本地 `app_init`、`app_dispatch` 钩子。安装正式插件时 FastAdmin 会从插件目录复制这些后台文件，并刷新插件钩子配置。修改后台文件后，打包前核对运行副本与插件源文件一致。

`uninstall.sql` 会删除全部棋手、房间、比赛、积分与会话数据，卸载前应备份。不要把 Node 参考服务和此插件同时作为正式对局入口。

部署环境每分钟运行一次 `php think pawchess:expire`，结算超时棋局并关闭等待超过 30 分钟的房间；房间请求也会即时检查超时。尚未接入真实微信账号、MySQL 和微信开发者工具做端到端验证，请在上线前完成联调。
