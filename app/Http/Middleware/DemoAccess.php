<?php

namespace App\Http\Middleware;

use Closure;
use Dcat\Admin\Admin;
use Dcat\Admin\Widgets\Form;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DemoAccess
{
    private const FORMS = [
        'App\\Admin\\Forms\\BulkNoticeForm' => ['action', 'content'],
        'App\\Admin\\Forms\\BalanceForm' => ['action', 'id', 'amount'],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Admin::guard()->user();
        if (! $user) {
            return $request->expectsJson()
                ? response()->json(['message' => '请先进入演示。'], 401)
                : redirect('/');
        }

        abort_unless(
            $user->username === 'demo'
            && (int) $user->getAuthIdentifier() > 1
            && ! $user->isAdministrator()
            && $user->roles->count() === 1
            && $user->isRole('demo'),
            403, '仅允许受限演示访客访问。'
        );

        $prefix = trim((string) config('admin.route.prefix', 'admin'), '/');
        $path = $request->decodedPath();
        abort_unless(str_starts_with($path, $prefix.'/'), 403);
        $path = substr($path, strlen($prefix) + 1);
        abort_if(preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x1f]~', $path), 403);

        // Upload/file deletion flags are forbidden even on otherwise allowed CRUD URLs.
        abort_if($request->allFiles() !== [] || $request->hasAny(['_file_', '_file_del_', '_upload_', '_uploader_', '_relation']), 403, '演示不支持上传或文件操作。');
        abort_if(strlen($request->getContent()) > 65536 || strlen((string) $request->getQueryString()) > 8192, 413);

        if ($path === 'kit/locale') {
            abort_unless($request->isMethod('POST') && $request->routeIs(admin_route_name('kit.locale')), 403);
            $this->allowFields($request, ['locale', '_token']);

            // This changes only the visitor's session; do not attach CRUD metadata.
            return $next($request);
        } elseif ($path === 'auth/logout') {
            abort_unless($request->isMethod('GET'), 403);
        } elseif ($path === 'dcat-api/form') {
            abort_unless($request->isMethod('POST') && $request->routeIs(admin_api_route_name('form')), 403);
            $class = $request->get(Form::REQUEST_NAME);
            abort_unless(is_string($class) && isset(self::FORMS[$class]), 403, '该表单不在演示白名单中。');
            $fields = array_merge(self::FORMS[$class], [Form::REQUEST_NAME, '_token', Form::CURRENT_URL_NAME]);
            if ($class === 'App\\Admin\\Forms\\BalanceForm') {
                $fields[] = Form::LAZY_PAYLOAD_NAME;
            }
            $this->allowFields($request, $fields);
            if ($class === 'App\\Admin\\Forms\\BalanceForm') {
                $this->balancePayload($request);
            }
        } elseif ($path === 'dcat-api/render') {
            abort_unless($request->isMethod('GET') && $request->routeIs(admin_api_route_name('render')), 403);
            $class = $request->get('renderable');
            abort_unless(is_string($class) && str_replace('_', '\\', $class) === 'App\\Admin\\Forms\\BalanceForm', 403, '该懒加载组件不在演示白名单中。');
            $this->allowFields($request, ['renderable', '_trans_', 'id', 'action', '_token', '_pjax', '_', Form::CURRENT_URL_NAME]);
            // LazyWidget includes the source URL while rendering. Keep its return target local.
            $request->query->set(Form::CURRENT_URL_NAME, admin_url('demo/actions'));
            if ($request->has('_trans_')) {
                abort_unless(is_string($request->get('_trans_')) && preg_match('/^[A-Za-z0-9_.-]{0,120}$/', $request->get('_trans_')), 403);
            }
        } else {
            abort_unless(preg_match('~^demo/[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~D', $path), 403, '演示环境不开放此功能。');
            abort_unless(in_array($request->method(), ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true), 403);
        }

        if (! $request->isMethodSafe()) {
            // Dcat uses Request::get(), where query parameters precede the body.
            // Remove query overrides before setting a local return path.
            // Dcat 会在 getCurrentUrl() 里对该值再执行一次 admin_url()，因此这里必须是完整
            // URL：写成 /admin/demo/records 这类已带前缀的路径会被二次拼接成
            // /admin/admin/demo/records，保存后跳转 404。
            $request->query->remove(Form::CURRENT_URL_NAME);
            $request->merge([Form::CURRENT_URL_NAME => admin_url($path === 'dcat-api/form' ? 'demo/actions' : 'demo/records')]);
            $key = 'demo-writes:'.hash('sha256', (string) $request->ip());
            if (RateLimiter::tooManyAttempts($key, 30)) {
                return response()->json(['message' => '操作过于频繁，每个 IP 每分钟最多写入 30 次。'], 429)
                    ->header('Retry-After', (string) RateLimiter::availableIn($key));
            }
            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }

    private function allowFields(Request $request, array $allowed): void
    {
        abort_if(array_diff(array_keys($request->all()), $allowed) !== [], 403, '表单包含不允许的字段。');
        foreach ($request->all() as $value) {
            abort_unless($value === null || is_scalar($value), 403);
            abort_if(is_string($value) && mb_strlen($value) > 2000, 422, '表单内容过长。');
        }
    }

    private function balancePayload(Request $request): void
    {
        $payload = json_decode((string) $request->input(Form::LAZY_PAYLOAD_NAME, '{}'), true);
        abort_unless(is_array($payload), 403, '无效的懒加载参数。');
        // Dcat serializes render transport metadata too. Validate and discard it;
        // only the business id/action may reach the form's payload resolver.
        abort_if(array_diff(array_keys($payload), ['id', 'action', 'renderable', '_trans_', Form::CURRENT_URL_NAME, '_token', '_pjax', '_']) !== [], 403);
        foreach ($payload as $value) {
            abort_unless($value === null || is_scalar($value), 403);
        }
        if (isset($payload['renderable'])) {
            abort_unless(str_replace('_', '\\', (string) $payload['renderable']) === 'App\\Admin\\Forms\\BalanceForm', 403);
        }
        $id = $request->input('id');
        abort_unless(filter_var($id, FILTER_VALIDATE_INT) !== false && (int) $id > 0, 422, '请选择有效记录。');
        abort_unless($request->input('action') === 'balance', 422);
        abort_if(isset($payload['id']) && (string) $payload['id'] !== (string) $id, 403);
        abort_if(isset($payload['action']) && $payload['action'] !== 'balance', 403);
        $request->merge([Form::LAZY_PAYLOAD_NAME => json_encode(['id' => (int) $id, 'action' => 'balance'])]);
    }
}
