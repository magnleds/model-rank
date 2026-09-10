# ModelRank · GOAT vs Go 性价比榜

对 **OpenCode Go** 与 **Command Code GOAT** 两平台模型做统一比价。

- **得分统一用 Command Code Intelligence**（灵魂），Opencode 同名模型自动映射补分
- **性价比排序** = 总回报 (Intelligence-30) × 月额度，默认置顶“分高且量大”
- **额度** = 月可请求数（Go官方Estimated requests / GOAT按credits反推）
- **NEW 高亮** = 首次收录 7 天内脉冲徽章
- **刷新** = 每天 03:00 自动一次 + 手动 5 次/天/IP 限流
- **UI** = Tailwind CDN 现代排行榜（shadcn 风格），无登录直达，移动端横滑
- 无需 npm/composer，PHP+SQLite 单文件部署

## 访问
https://up.oslla.com/apps-xLnyCIJ8qmW6mI/Claude/services/model-rank/

## API
- `GET api.php?action=list_models&q=&source=&only_new=&sort=value|intelligence|req_month|tps`
- `POST api.php?action=refresh` (5次/天/IP)
- `GET cron.php` (cron 每日 03:00)

## 结构
`db.php` 建表 + meta | `fetcher.php` 双源抓取 + do_refresh | `api.php` 列表/刷新 | `index.php` 前端 | `cron.php` 自动任务
