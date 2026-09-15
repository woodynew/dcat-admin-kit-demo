# Demo 多语言开发指南

## 当前能力与依赖状态

当前源码已启用 Kit 的 `features.locale_switcher`，支持简体中文、繁體中文和 English。后台的侧栏、页内导航、标题、表头、工具、组件说明及 iframe 菜单会跟随切换。

共享记录、日志、用于验证中文与特殊字符的固定样例、Reflection 提取的实际源码保留原文。公开欢迎页目前为中文。此能力不包含业务数据的多语言存储或自动翻译。

Demo 依赖 Packagist 上已发布的 Kit 与 iframe，锁定的版本以 `composer.lock` 为准；多语言能力由这两个包提供。

Git 忽略的本地覆盖文件仍可挂载同级源码做联调；此时 `vendor` 中的代码可能与锁文件版本不同，不要把锁文件版本号、宿主机 `vendor`、Docker 容器内加载的代码视为同一个状态。

## 代码入口

| 文件 | 职责 |
| --- | --- |
| `config/dcat-admin-kit.php` | 开启语言选择器 |
| `app/Providers/AppServiceProvider.php` | 注册 JSON 语言包；在特性预览中保留语言开关；翻译默认访客显示名 |
| `app/Admin/bootstrap.php` | 在当前语言下生成站点标题、菜单和 MultiRow 字段标签 |
| `resources/translations/*.json` | Demo 界面文案 |
| `app/Support/DemoExamples.php` | Grid、Form、Show、工具及组件说明的翻译调用 |
| `resources/views/demo/*.blade.php` | 页面文案及前端提示的转义输出 |
| `app/Http/Middleware/DemoAccess.php` | 允许受限访客调用指定的语言切换接口 |
| `tests/Browser/locale.spec.js` | 真实 Demo 的可见文案、切换与布局回归 |

## 添加或修改文案

Demo 使用中文原文作为 Laravel JSON 翻译键。例如在三个语言包中分别维护：

| 文件 | JSON 条目 |
| --- | --- |
| `zh_CN.json` | `"标题": "标题"` |
| `zh_TW.json` | `"标题": "標題"` |
| `en.json` | `"标题": "Title"` |

新增或更改原文键时，同步修改三个文件及调用处，避免某一种语言回退为中文。

PHP 与 Blade 的使用方式：

```php
$grid->column('title', __('标题'));
$content->title(__('列显示器'));
```

```blade
<h2>{{ __('列显示器') }}</h2>
```

JSON 翻译路径由 `AppServiceProvider::register()` 的 `loadJsonTranslationsFrom(resource_path('translations'))` 注册。这里只注册路径，不提前计算当前请求的文案。使用独立的 `resources/translations`，避免新建 `resources/lang` 改变 Laravel 自动识别的语言目录，影响 Dcat 语言包发布。

PHP 拼接 HTML 时对翻译文本使用 `e()`；不能把所有包含中文的字符串都机械翻译，测试样例、实际源码和持久业务内容应保持原意。

## 菜单、字段与请求时机

Kit 在 Session 启动后设置当前请求的 Laravel locale 和 `app.locale`。Demo 在后台 `bootstrap.php` 中使用当前语言生成：

- `admin.title`、`admin.logo`；
- Dcat 菜单的 `menu.titles.<原始菜单标题>`；
- MultiRow 使用的 `global.fields.<字段名>`。

例如已有菜单标题为「列显示器」时：

```php
app('translator')->addLines([
    'menu.titles.列显示器' => __('列显示器'),
    'global.fields.title' => __('标题'),
], app()->getLocale());
```

不要将右侧值写成固定中文后注册到当前 locale；那会让英文页面继续显示中文。菜单只在渲染时翻译，无需改数据库菜单记录，也不要在切换语言时批量改写用户或业务数据。

Demo 的特性预览以 `config/dcat-admin-kit.php` 为站点基线，只翻转当前比较的那一个开关；站点已开启的 `grid_assets`、`locale_switcher` 在预览页保持开启。新增预览逻辑时不要把已开启的开关一起关掉，否则对比页会退回未定制的样式。

## 前端文案和布局

复制源码的反馈通过 Blade 的 `data-copy-success` 和 `data-copy-failed` 属性提供，JavaScript 从 `dataset` 读取。新增前端提示可复用这种做法，避免 JS 中写死中文；如需内联 JSON，使用带 `JSON_HEX_*` 标记的安全编码。

语言选择器样式由 Kit 自身维护。导航栏内应检查三种语言的文字高度和下拉标识；不要额外叠加会造成行高、内边距冲突的 `form-control-sm`。英文站点标题使用较短的 `Playground`，避免超过侧栏宽度。

PJAX 跳转仍应使用当前 Session 的语言；切换语言本身刷新同源顶层页面。iframe 扩展会清理旧语言的标签 HTML 缓存并重新打开首页，切换前应保存编辑内容。

## 访客访问白名单

语言切换是 `POST /admin/kit/locale`，路由名称通过 `admin_route_name('kit.locale')` 获取。DemoAccess 仅放行该命名路由的 POST 请求，允许字段为 `locale`、`_token`；不为这个 Session 偏好操作附加 CRUD 返回地址参数。

登录与 CSRF 保护仍然保留，语言代码由 Kit 按配置白名单校验。不要通过放开整个 `/admin/kit/*` 或移除访客权限校验来接入切换器。

Dcat 会为扩展路由自动加名称前缀。扩展声明使用相对名称 `kit.locale`；调用方判断时使用完整名称，避免出现重复的 `dcat.admin` 前缀。

## 本地源码联调

本机的 `compose.override.yaml`、`composer.local.*`、`docker/local-kit.ini` 和 `LOCAL_KIT.md` 为忽略的本地文件，不会随普通提交或克隆自动带走。正式依赖已升级到已发布版本（以 `composer.lock` 为准）；继续开发新功能时按同一流程发布后再更新依赖。

新机器需要自行配置本地覆盖。PHP 容器必须加载新 Kit、iframe 及 Demo 应用代码；Nginx 必须加载相同版本的发布资源。例如在已有 Compose 配置中合并以下挂载，保留原来的持久数据卷：

```yaml
services:
  php:
    volumes:
      - ../dcat-admin-kit:/www/vendor/woodynew/dcat-admin-kit:ro
      - ../dcat-iframe-tab:/www/vendor/woodynew/z-dcat-iframe-tab:ro
      - ./app:/www/app:ro
      - ./config/dcat-admin-kit.php:/www/config/dcat-admin-kit.php:ro
      - ./resources/translations:/www/resources/translations:ro
      - ./resources/views/demo:/www/resources/views/demo:ro
      - ./public/demo:/www/public/demo:ro
  nginx:
    volumes:
      - ../dcat-admin-kit/resources/assets:/www/public/vendor/dcat-admin-extensions/woodynew/dcat-admin-kit:ro
      - ../dcat-iframe-tab/src/assets/js/compress:/www/public/vendor/iframe-tab/js:ro
      - ./public/demo:/www/public/demo:ro
```

在基础镜像已经构建后，应用覆盖并重建两个容器的挂载：

```bash
docker compose up -d --no-build --force-recreate --wait
```

PHP 与 Nginx 都需更新；只重建 PHP 时，Nginx 可能仍使用旧的上游地址。配置缓存、Blade 缓存或 OPcache 也可能影响热更新：修改配置后重建配置缓存，覆盖视图后必要时清理视图缓存；本地开发可开启 OPcache 时间戳检查，或重启 PHP 服务。

不要用删除持久数据卷的方式刷新代码。源码挂载不会改变 Composer 显示的包版本号。使用 `docker inspect` 检查实际挂载、`route:list --path=kit/locale` 检查路由，并比对容器 PHP 文件和 HTTP 返回的 iframe JS 与本地文件内容。

## 回归验证

PHP 8.4 环境下运行：

```bash
php artisan test
PLAYWRIGHT_BASE_URL=http://127.0.0.1:18085 npm run test:browser -- tests/Browser/locale.spec.js
```

上面的浏览器用例在已有本地 Demo 中登录访客并切换语言，不创建业务记录。完整浏览器套件还包含共享记录的写入测试，使用前按 README 准备测试数据。

检查内容包括英文侧栏、页内导航、标题、说明、表头、MultiRow 标签和访客名称；通过 PJAX 进入操作、记录、组件页面；检查特性预览和 iframe 中的切换；确认选择框文字可见、站点标题未被裁切，再切回简中。不能仅以选择框值或 `<html lang>` 变化判定多语言已经完成。
