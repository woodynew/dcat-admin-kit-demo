# Docker 一键启动

目录参考趣拉新项目：`Dockerfile` 构建镜像，`common/` 统一存放 Nginx、PHP-FPM、Supervisor 与启动脚本。本项目使用公开镜像和独立 SQLite，不需要私有镜像仓库、宿主机 PHP、Composer 或 Node。

从仓库根目录执行（需要 Docker Engine 与 Docker Compose）：

```bash
docker compose up -d --build --wait
```

访问 <http://localhost:18085>。首次构建会下载镜像和锁定的 Composer 生产依赖；启动自动生成并保存密钥、初始化 SQLite、安装扩展，随后 Supervisor 运行 PHP-FPM 与 `schedule:work`。Nginx 等 PHP 健康后才启动，`--wait` 等待网页健康检查成功。

## 目录与服务

- 根目录 `compose.yaml`：`php` 与 `nginx` 两个服务、一份 `demo-data` 命名数据卷。
- `docker/Dockerfile`：PHP 8.4 镜像中构建完整应用，再把同一版本的公开资源复制到 Nginx 镜像。
- `docker/common/entrypoint.sh`：每次启动运行幂等 `demo:install`，不会执行 `demo:reset`，不会在启动时下载或更新依赖。
- `docker/common/conf/supervisord.conf`：管理 PHP-FPM 与每小时重置所需的 Laravel 调度进程。
- `docker/common/php84/`、`docker/common/nginx/`：应用专用运行配置。

代码及资源随镜像交付，不挂载宿主机项目目录。`.dockerignore` 排除宿主机 `.env`、数据库、vendor、Node 和测试产物，容器不会接触本机演示数据库或其他项目配置。

## 端口与访问地址

默认只监听本机 `127.0.0.1:18085`，可用命令行环境变量覆盖：

```bash
DEMO_PORT=18086 DEMO_URL=http://localhost:18086 docker compose up -d --build --wait
```

需要从其他机器访问时显式指定 `DEMO_BIND=0.0.0.0`，并把 `DEMO_URL` 设置为真实访问地址。端口与 URL 是两个独立配置，修改端口时应一起修改 URL；这些变量无需写入宿主机 Laravel `.env`。

这是普通 HTTP 演示容器；公网部署时由外层反向代理终止 HTTPS，并按服务器策略配置代理与转发头。本项目默认单实例 SQLite，不要横向扩容 `php` 服务。

## 数据保留与维护

数据卷中保存 `/data/database.sqlite`、`/data/runtime.env`（自动生成的 APP_KEY）及 `/data/storage`（会话、缓存、共享写锁）。容器重启、重建或普通 `down` 后再次启动会保留这些数据；**每小时整点的计划重置仍会清除演示记录和日志**，账号、权限和扩展状态保留。

```bash
# 查看健康状态、日志和后台进程
docker compose ps
docker compose logs --tail=100 php nginx
docker compose exec php supervisorctl -c /etc/supervisord.conf status
docker compose exec --user www-data php php artisan schedule:list

# 重启或停止，保留数据卷
docker compose restart
docker compose down

# 手动重置共享演示记录（所有访客都会受到影响）
docker compose exec --user www-data php php artisan demo:reset

# 获取新代码后更新镜像与容器，仍保留数据卷
docker compose up -d --build --wait
```

不要把 `docker compose down -v` 作为普通停止命令：它会删除数据卷，同时丢失数据库、会话及密钥。备份时应停止服务并备份整份数据卷，以保持 SQLite 与其日志文件一致。

PHP 与 Nginx 输出日志到容器标准输出，并限制日志轮转大小。PHP 健康检查验证 FPM 和调度进程存活，Nginx 健康检查通过 Laravel `/up` 验证请求链路；启动失败可先看 `docker compose logs php`。

## 验证容器版本

GitHub Actions 的 Docker 任务从干净环境构建启动，并校验强制重建容器后密钥、账号和演示记录保持不变。

也可以在宿主机使用现有浏览器套件验证容器中的全部组件（需要 Node.js 22 和 Chrome）：

```bash
npm ci
PLAYWRIGHT_BASE_URL=http://127.0.0.1:18085 npm run test:browser
```

设置 `PLAYWRIGHT_BASE_URL` 后不会再启动宿主机 PHP 服务，测试仅操作该地址的演示数据。测试不应在每小时整点附近与计划重置同时执行。
