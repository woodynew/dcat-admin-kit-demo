# Dcat Admin Kit Demo

基于 Laravel 的中文交互演示项目，展示 Dcat Admin Kit 的列显示器、表单、操作工具及真实 iframe 多标签页。项目仓库：[woodynew/dcat-admin-kit-demo](https://github.com/woodynew/dcat-admin-kit-demo)。

## 技术版本

| 组件 | 当前锁定基线 |
| --- | --- |
| PHP | 8.4 |
| Laravel | 12.69.1 |
| woodynew/dcat-laravel-admin | 2.2.4 |
| woodynew/dcat-admin-kit | 0.1.0 |
| woodynew/z-dcat-iframe-tab | 1.2.1 |
| 数据库 | SQLite |

具体依赖以 `composer.lock` 为准。Node.js 22 和 npm 仅用于浏览器测试，站点运行使用发布到 `public/vendor` 的 Dcat 资源，无需 npm 安装或 Vite 构建。

## 本地安装

准备 PHP 8.4、Composer 2，以及 Laravel 所需 PHP 扩展，包括 `pdo_sqlite`、`sqlite3`、`mbstring`、`xml`、`curl`、`fileinfo`。以下命令中的 `php` 和 Composer 均应使用 PHP 8.4。

```bash
git clone https://github.com/woodynew/dcat-admin-kit-demo.git
cd dcat-admin-kit-demo
composer install
cp .env.example .env
php artisan key:generate
php artisan demo:install
php artisan serve --host=127.0.0.1 --port=18084
```

在 macOS Herd 中可将 `php` 替换成 `php84`；运行 Composer 时也应确认其 PHP 解释器为 8.4。默认使用 `database/database.sqlite`，安装命令执行迁移、初始化演示数据及受限身份、发布后台和扩展资源。应用数据库与后台数据库必须指向同一个本地 SQLite 文件。

访问 [本地首页](http://127.0.0.1:18084)，点击进入演示。首页通过带 CSRF 令牌的 `POST /demo/enter` 自动登录受限的 `demo` 账户，随后跳转到 `/admin/demo/overview`；无需输入账号密码，也不提供固定的 `admin/admin` 登录凭据。

`demo:install` 可重复执行：保留已有业务记录和应用配置，补齐安装所需数据与资源。它不等同于重置命令。后台演示身份只开放演示页面及必要的表单接口，不开放用户、权限、扩展管理或文件上传。

## 演示内容

| 页面 | 地址 | 可体验内容 |
| --- | --- | --- |
| 演示概览 | `/admin/demo/overview` | 功能入口、共享数据说明 |
| 列显示器 | `/admin/demo/columns` | 二维码、复制、链接、截断、长文本弹窗及别名 |
| 共享记录 | `/admin/demo/records` | 新建、编辑、复制记录、顶部提交按钮 |
| 操作与工具 | `/admin/demo/actions` | 工具栏通知、行弹窗、演示余额与日志写入 |
| 组件展示 | `/admin/demo/widgets` | Kit 组件示例 |
| 全局特性 | `/admin/demo/features` | 后台全局特性说明 |
| 多标签页 | `/admin/demo/tabs` | iframe 扩展实际打开的页面标签 |

操作会写入共享的 SQLite 数据，所有访客都能看到其他人的修改。通知仅写入演示日志，不发送外部消息；余额仅为演示数字，不涉及真实资金。请勿录入真实个人资料、密码、令牌或业务数据。

组件用法见 [Kit 组件说明](https://github.com/woodynew/dcat-admin-kit#components)、[Dcat Admin 基础文档](https://learnku.com/docs/dcat-admin/2.x) 和本页的安装、演示及测试章节。

演示包含两处应用内集成适配：Dcat 基础包 2.2.4 会将 `AdminGridHrefTool` 输出为带 `href` 的按钮，`DemoExamples::linkTool` 使用小型匿名子类渲染原生链接，页面可查看实际源码；iframe Provider 子类仅在 iframe 请求标记下启用紧凑布局，保留扩展包提供的真实标签容器。上述适配位于本应用，未修改已发布的依赖包。

当前 Kit 0.1.0 与基座 2.2.4 组合下，自定义二维码宽高未按传入值生效，演示使用已验证的默认 200×200 尺寸。该上游问题未在 Demo 中重写组件修复。

## 重置与限制

共享记录最多 200 条，操作日志最多保留最近 500 条；每个 IP 每分钟最多写入 30 次。遇到限流时等待后重试，记录可能因其他访客编辑或定时重置而失效，此时请刷新页面。

```bash
# 手动恢复演示样例，会清除现有演示记录和演示日志
php artisan demo:reset

# 本地另开终端运行调度器
php artisan schedule:work
```

调度器每小时整点运行 `demo:reset`。重置与演示写入共用写锁，并在数据库事务内执行；仅重置演示数据表，不清空后台账户、角色、权限和扩展配置。未运行调度器时不会自动重置。重置会影响所有正在访问的演示用户，勿将演示数据库接入正式业务。

## 测试

```bash
composer validate --strict
php artisan test
vendor/bin/pint --test

# 浏览器测试独立安装 npm 开发依赖
npm ci
# macOS 已安装 Google Chrome 时，无需下载浏览器
PHP_BINARY=php84 PLAYWRIGHT_CHANNEL=chrome npm run test:browser
```

浏览器测试前需完成上述 `.env`、密钥与 `demo:install` 初始化。测试会通过实际页面创建和修改演示记录、写入通知与操作日志，请仅针对本地测试数据运行。PHPUnit 使用其独立测试配置；不要让它与浏览器测试共享持久数据库。

浏览器验收覆盖首页 CSRF 登录，列复制与二维码、文本展开和 PJAX 往返，顶部提交的创建/编辑/复制，快捷状态更新与请求形态日志，顶部返回，通知和余额弹窗写入，普通详情跳转、真实 iframe 标签创建/复用/关闭，以及 8 个全局开关的关闭/开启/恢复对照。测试读取真实页面、剪贴板和保存后的数据，不模拟 iframe 扩展接口。

Playwright 默认在本地使用已安装的 Chrome，CI 使用 Chromium。其他环境如需使用 Playwright 自带的 Chromium：

```bash
npx playwright install --with-deps chromium
PLAYWRIGHT_CHANNEL=chromium npm run test:browser
```

测试固定串行运行（`workers: 1`），通过 `PHP_BINARY` 指定 PHP，默认是 `php`。测试会启动 `php artisan serve --host=127.0.0.1 --port=18084`；本地若该端口已有本项目服务则复用，CI 必须启动独立服务。失败时可查看 `test-results` 内的截图、跟踪文件；HTML 报告使用 `npm run test:browser:report`。不在浏览器套件内打满限流额度，以免阻塞后续真实 UI 验证。

GitHub Actions 分别运行 PHP 检查与浏览器测试。浏览器任务单独初始化 SQLite、发布资源并安装 Chromium，不依赖 PHPUnit 运行产生的数据。不要提交 `.env`、临时测试环境文件、SQLite 数据库、测试产物或 `node_modules`。

## 部署

部署到专用演示环境，Web 服务的根目录必须为本项目的 `public`，并确保 PHP-FPM 使用 PHP 8.4。安装生产依赖后配置 `.env`、生成密钥并运行 `demo:install`：

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan demo:install
php artisan config:cache
php artisan view:cache
```

将 `APP_ENV` 设置为 `production`、`APP_DEBUG=false`、`APP_URL` 设置为实际 HTTPS 地址，再生成配置缓存。升级已有站点时保留原 `.env` 和 `APP_KEY`，无需重复复制环境文件或生成密钥。本文不提供尚未部署的在线演示地址。

持久化 `database/database.sqlite` 及其所在目录，SQLite 的日志文件也需要目录写权限；`storage` 和 `bootstrap/cache` 必须可写。保留会话、缓存和锁所需的存储目录，避免多实例使用各自的本地 SQLite 或锁目录。演示采用单实例、共享本地存储部署，不应连接生产数据库。

为部署用户配置每分钟调度（替换路径与 PHP 可执行文件）：

```cron
* * * * * cd /srv/dcat-admin-kit-demo && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

该调度负责触发每小时重置。确认 PHP 版本、运行用户和目录权限与 Web 服务一致，并定期检查应用日志及磁盘空间。

## 许可证

本项目采用 [MIT 许可证](LICENSE)。
