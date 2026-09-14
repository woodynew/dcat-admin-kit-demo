<?php

namespace Tests\Feature;

use App\Console\Commands\DemoInstall;
use App\Http\Controllers\DemoEntryController;
use App\Http\Middleware\DemoAccess;
use App\Models\DemoLog;
use App\Models\DemoRecord;
use App\Models\DemoState;
use App\Services\DemoData;
use Dcat\Admin\Admin;
use Dcat\Admin\Models\Administrator;
use Dcat\Admin\Models\Menu;
use Dcat\Admin\Models\Permission;
use Dcat\Admin\Models\Role;
use Dcat\Admin\Widgets\Form;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DemoBackendTest extends TestCase
{
    private DemoData $data;

    protected function setUp(): void
    {
        parent::setUp();
        // Never run RefreshDatabase against the application's configured live file.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null, 'admin.database.connection' => 'sqlite', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->assertSame(0, Artisan::call('migrate', ['--path' => ['vendor/woodynew/dcat-laravel-admin/database/migrations', 'database/migrations'], '--force' => true]));
        $this->data = app(DemoData::class);
        $this->data->installData();
    }

    public function test_install_is_idempotent_and_identity_is_restricted(): void
    {
        $record = DemoRecord::query()->first();
        $this->data->update($record, ['title' => '访客保留的编辑']);
        $marker = DemoState::query()->find('last_reset_at')->value;
        $user = Administrator::query()->where('username', 'demo')->firstOrFail();
        $password = $user->password;
        $menu = Menu::query()->where('uri', 'demo/overview')->firstOrFail();
        $menu->update(['title' => '自定义菜单名称']);
        $this->data->installData();
        $this->assertSame('访客保留的编辑', $record->fresh()->title);
        $this->assertSame($marker, DemoState::query()->find('last_reset_at')->value);
        $this->assertSame($password, $user->fresh()->password);
        $this->assertGreaterThan(1, $user->id);
        $this->assertFalse($user->isAdministrator());
        $this->assertSame(['demo'], $user->roles->pluck('slug')->all());
        $this->assertGreaterThan(1, $user->roles->first()->id);
        $this->assertFalse(Hash::check('demo', $password));
        $this->assertFalse(Hash::check('admin', $password));
        $this->assertNull(Administrator::query()->where('username', 'admin')->first());
        $this->assertSame('自定义菜单名称', $menu->fresh()->title);
        $this->assertSame(7, Menu::query()->count());
        $this->assertSame(['demo/*', 'POST:dcat-api/form', 'GET:dcat-api/render'], Permission::query()->where('slug', 'demo')->first()->http_path);
        foreach (DemoRecord::all() as $sample) {
            $this->assertLessThanOrEqual(120, mb_strlen($sample->title));
            $this->assertStringNotContainsString('<', (string) $sample->description);
            $this->data->update($sample, ['title' => $sample->title]);
        }
    }

    public function test_install_command_repeats_migrations_and_non_forced_publication_without_base_seeder(): void
    {
        $database = sys_get_temp_dir().'/dcat-demo-backend-'.bin2hex(random_bytes(12)).'.sqlite';
        $this->assertFileDoesNotExist($database);
        config(['database.connections.sqlite.database' => $database]);
        DB::purge('sqlite');
        try {
            $command = $this->installationCommand(2);
            $this->assertSame(0, $command->handle($this->data));
            $this->assertFileExists($database);
            $this->assertSame($database, DB::connection()->getDatabaseName());
            $record = $this->data->create(['title' => '安装后保留', 'code' => 'PRESERVED']);
            $this->assertSame(0, $command->handle($this->data));
            $this->assertNotNull($record->fresh());
            $this->assertSame(1, Administrator::query()->where('username', 'demo')->count());
        } finally {
            DB::purge('sqlite');
            foreach ([$database, $database.'-wal', $database.'-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    private function installationCommand(int $times): DemoInstall
    {
        $command = Mockery::mock(DemoInstall::class)->makePartial();
        $command->setLaravel($this->app);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        $command->shouldReceive('call')->with('migrate', ['--path' => ['vendor/woodynew/dcat-laravel-admin/database/migrations', 'database/migrations'], '--force' => true])->times($times)->andReturnUsing(fn ($name, $args) => Artisan::call($name, array_merge($args, ['--no-interaction' => true])));
        foreach (['dcat-admin-config', 'dcat-admin-assets', 'dcat-admin-lang', 'iframe-tab', 'iframe-tab.config'] as $tag) {
            $command->shouldReceive('call')->with('vendor:publish', ['--tag' => $tag])->times($times)->andReturn(0);
        }
        $command->shouldReceive('call')->with('dcat-admin-kit:install')->times($times)->andReturn(0);

        return $command;
    }

    public function test_reset_repeats_without_touching_admin_or_extensions_or_reusing_record_ids(): void
    {
        $old = DemoRecord::query()->first();
        $admin = Administrator::query()->get()->toArray();
        $roles = Role::query()->get()->toArray();
        DB::table('admin_extensions')->insert(['name' => 'test/extension', 'version' => '1.0', 'is_enabled' => 1]);
        $extensions = DB::table('admin_extensions')->get()->toArray();
        $this->travel(1)->hours();
        $this->assertSame(0, Artisan::call('demo:reset'));
        $firstMarker = DemoState::query()->find('last_reset_at')->value;
        $this->travel(1)->hours();
        $this->assertSame(0, Artisan::call('demo:reset'));
        $this->assertSame(8, DemoRecord::query()->count());
        $this->assertSame(1, DemoLog::query()->count());
        $this->assertNotSame($firstMarker, DemoState::query()->find('last_reset_at')->value);
        $this->assertSame($admin, Administrator::query()->get()->toArray());
        $this->assertSame($roles, Role::query()->get()->toArray());
        $this->assertEquals($extensions, DB::table('admin_extensions')->get()->toArray());
        $this->assertNull($old->fresh());
        $this->assertValidation(fn () => $this->data->update($old, ['title' => '过期提交']), 'id');
        $this->assertValidation(fn () => $this->data->delete($old), 'id');
        $this->assertValidation(fn () => $this->data->addBalance($old->id, '1.00'), 'id');
    }

    public function test_writes_validate_fields_limits_and_optimistic_staleness(): void
    {
        $record = $this->data->create(['title' => '测试', 'code' => 'VALIDATE', 'balance' => '0.10']);
        $stale = $record->fresh();
        $updated = $this->data->update($record, ['title' => '新的标题']);
        $this->assertValidation(fn () => $this->data->update($stale, ['title' => '覆盖']), 'id');
        $this->assertValidation(fn () => $this->data->delete($stale), 'id');
        $this->assertValidation(fn () => $this->data->create(['title' => str_repeat('中', 121), 'code' => 'LENGTH']), 'title');
        $this->assertValidation(fn () => $this->data->update($updated, ['description' => '<img src=x onerror=alert(1)>']), 'description');
        $this->assertValidation(fn () => $this->data->update($updated, ['url' => 'javascript:alert(1)']), 'url');
        $this->assertValidation(fn () => $this->data->update($updated, ['description' => str_repeat('中', 2001)]), 'description');
        $this->assertValidation(fn () => $this->data->create(['title' => '重复', 'code' => 'VALIDATE']), 'code');
        $this->assertValidation(fn () => $this->data->addBalance($record->id, '0.001'), 'amount');
        $this->assertValidation(fn () => $this->data->addBalance($record->id, '-1.00'), 'amount');
        $this->data->addBalance($record->id, '0.20');
        $this->data->addBalance($record->id, '0.10');
        $this->assertSame('0.40', $record->fresh()->balance);
        $this->assertSame(2, DemoLog::query()->where('action', 'balance')->where('demo_record_id', $record->id)->count());
        $this->assertValidation(fn () => $this->data->notify(str_repeat('中', 2001)), 'message');
        $this->assertValidation(fn () => $this->data->log('note', 'missing', 999999), 'id');
    }

    public function test_record_and_log_caps_keep_newest_events(): void
    {
        $rows = [];
        for ($i = DemoRecord::query()->count(); $i < 200; $i++) {
            $rows[] = ['title' => '限额样本', 'code' => 'CAP_'.$i];
        }
        DemoRecord::query()->insert($rows);
        $this->assertValidation(fn () => $this->data->create(['title' => '超限', 'code' => 'OVER_CAP']), 'title');
        $this->assertSame(200, DemoRecord::query()->count());
        $logs = [];
        for ($i = 0; $i < 501; $i++) {
            $logs[] = ['action' => 'fixture', 'message' => 'event '.$i];
        }
        DemoLog::query()->insert($logs);
        $this->data->notify('保留最新通知');
        $this->assertSame(500, DemoLog::query()->count());
        $this->assertSame('保留最新通知', DemoLog::query()->latest('id')->first()->message);
    }

    public function test_record_and_balance_changes_roll_back_if_logging_fails(): void
    {
        $record = DemoRecord::query()->first();
        $count = DemoRecord::query()->count();
        DemoLog::creating(fn () => throw new \RuntimeException('simulated log failure'));
        try {
            foreach ([fn () => $this->data->create(['title' => '必须回滚', 'code' => 'ROLLBACK']), fn () => $this->data->addBalance($record->id, '5.00'), fn () => $this->data->reset()] as $write) {
                try {
                    $write();
                    $this->fail('Expected transaction failure');
                } catch (\RuntimeException $exception) {
                    $this->assertSame('simulated log failure', $exception->getMessage());
                }
                $this->assertSame($count, DemoRecord::query()->count());
                $this->assertSame($record->balance, $record->fresh()->balance);
            }
        } finally {
            DemoLog::flushEventListeners();
        }
    }

    public function test_every_public_write_uses_the_same_cache_lock(): void
    {
        $record = DemoRecord::query()->first();
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('block')->with(5, Mockery::type(\Closure::class))->times(8)->andThrow(new LockTimeoutException);
        Cache::shouldReceive('lock')->with(DemoData::LOCK_KEY, 120)->times(8)->andReturn($lock);
        foreach ([fn () => $this->data->installData(), fn () => $this->data->reset(), fn () => $this->data->create([]), fn () => $this->data->update($record, []), fn () => $this->data->delete($record), fn () => $this->data->log('note', 'test'), fn () => $this->data->addBalance($record->id, '1.00'), fn () => $this->data->notify('test')] as $write) {
            $this->assertValidation($write, 'demo');
        }
        $this->assertNotNull($record->fresh());
    }

    public function test_entry_logs_in_only_demo_and_rotates_session(): void
    {
        Route::post('/_backend-test/enter', DemoEntryController::class)->middleware('web');
        $this->withSession(['url.intended' => 'https://evil.example/']);
        $oldSession = session()->getId();
        $this->post('/_backend-test/enter', ['username' => 'demo', 'user_id' => 1])->assertRedirect(admin_url('demo/overview'));
        $this->assertSame('demo', Admin::guard()->user()->username);
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertFalse(session()->has('url.intended'));
        $this->postJson('/_backend-test/enter', ['username' => 'admin'])->assertUnprocessable();
    }

    public function test_default_admin_password_is_invalidated_and_elevated_demo_is_rejected(): void
    {
        $default = Administrator::query()->create(['username' => 'admin', 'name' => 'Default', 'password' => Hash::make('admin')]);
        $this->data->installData();
        $this->assertFalse(Hash::check('admin', $default->fresh()->password));
        $user = Administrator::query()->where('username', 'demo')->firstOrFail();
        $role = Role::query()->create(['slug' => 'administrator', 'name' => 'Administrator']);
        $user->roles()->attach($role->id);
        Admin::guard()->setUser($user->fresh());
        $this->assertGuardRejected('GET', 'demo/overview', [], 403);
        Route::post('/_backend-test/elevated-entry', DemoEntryController::class)->middleware('web');
        $this->postJson('/_backend-test/elevated-entry', ['username' => 'demo'])->assertForbidden();
    }

    public function test_guest_and_direct_sensitive_urls_are_blocked(): void
    {
        $this->assertSame(302, $this->guardRequest('GET', 'demo/overview')->getStatusCode());
        $this->assertSame(url('/'), $this->guardRequest('GET', 'demo/overview')->headers->get('Location'));
        $this->demoLogin();
        foreach ([['GET', 'auth/users'], ['POST', 'auth/login'], ['GET', 'auth/login'], ['PUT', 'auth/setting'], ['GET', 'auth/roles'], ['GET', 'auth/extensions'], ['POST', 'dcat-api/form/upload'], ['POST', 'dcat-api/action'], ['POST', 'dcat-api/value'], ['GET', 'helpers/scaffold']] as [$method, $path]) {
            $this->assertGuardRejected($method, $path, [], 403);
        }
        $this->assertSame(200, $this->guardRequest('GET', 'demo/tabs')->getStatusCode());
        $this->assertSame(200, $this->guardRequest('GET', 'auth/logout')->getStatusCode());
        $this->assertGuardRejected('POST', 'demo/records', ['_file_' => 'avatar'], 403);
    }

    public function test_locale_switch_allows_only_the_named_post_route_and_expected_fields(): void
    {
        $this->assertSame(302, $this->guardRequest('POST', 'kit/locale', ['locale' => 'en'])->getStatusCode());
        $this->demoLogin();
        $response = $this->guardRequest('POST', 'kit/locale', ['locale' => 'en', '_token' => 'test']);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['locale' => 'en', '_token' => 'test'], json_decode($response->getContent(), true));
        $this->assertGuardRejected('GET', 'kit/locale', ['locale' => 'en'], 403);
        $this->assertGuardRejected('POST', 'kit/locale', ['locale' => 'en', 'role_id' => 1], 403);
        $this->assertGuardRejected('POST', 'kit/other', ['locale' => 'en'], 403);
    }

    public function test_form_allowlist_canonicalizes_lazy_payload_and_return_url(): void
    {
        $this->demoLogin();
        $id = DemoRecord::query()->first()->id;
        $input = ['_form_' => 'App\\Admin\\Forms\\BalanceForm', 'id' => $id, 'action' => 'balance', 'amount' => '10.00', '_current_' => 'https://evil.example/', '_payload_' => json_encode(['id' => $id, 'action' => 'balance', 'renderable' => 'App_Admin_Forms_BalanceForm', '_trans_' => 'demo', '_current_' => 'https://evil.example/'])];
        $response = $this->guardRequest('POST', 'dcat-api/form', $input);
        $payload = json_decode($response->getContent(), true);
        $this->assertSame(['id' => $id, 'action' => 'balance'], json_decode($payload['_payload_'], true));
        $this->assertSame('/admin/demo/actions', $payload['_current_']);
        $request = Request::create('/admin/dcat-api/form?_current_=https://evil.example/', 'POST', $input);
        $route = new RoutingRoute(['POST'], 'admin/dcat-api/form', fn () => null);
        $route->name(admin_api_route_name('form'));
        $request->setRouteResolver(fn () => $route);
        app(DemoAccess::class)->handle($request, function (Request $request) {
            $this->assertSame('/admin/demo/actions', $request->get(Form::CURRENT_URL_NAME));

            return response('ok');
        });
        $this->assertGuardRejected('POST', 'dcat-api/form', array_replace($input, ['_form_' => 'Dcat\\Admin\\Widgets\\Form']), 403);
        $this->assertGuardRejected('POST', 'dcat-api/form', array_replace($input, ['_payload_' => '{bad']), 403);
        $this->assertGuardRejected('POST', 'dcat-api/form', array_replace($input, ['_payload_' => json_encode(['id' => $id + 1])]), 403);
        $this->assertGuardRejected('POST', 'dcat-api/form', array_replace($input, ['_payload_' => json_encode(['class' => 'Evil'])]), 403);
        $this->assertGuardRejected('POST', 'dcat-api/form', array_replace($input, ['role_id' => 1]), 403);
        $this->assertGuardRejected('GET', 'dcat-api/render', ['renderable' => 'Dcat_Admin_Grid_LazyRenderable'], 403);
        $render = $this->guardRequest('GET', 'dcat-api/render', ['renderable' => 'App_Admin_Forms_BalanceForm', 'id' => $id, 'action' => 'balance', '_current_' => 'https://evil.example/']);
        $this->assertSame(200, $render->getStatusCode());
        $this->assertSame('/admin/demo/actions', json_decode($render->getContent(), true)['_current_']);
        $this->assertSame(200, $this->guardRequest('POST', 'dcat-api/form', ['_form_' => 'App\\Admin\\Forms\\BulkNoticeForm', 'content' => '测试通知', 'action' => 'notice'])->getStatusCode());
    }

    public function test_write_limit_is_shared_by_ip_but_not_by_username(): void
    {
        $this->demoLogin();
        for ($i = 0; $i < 30; $i++) {
            $this->assertSame(200, $this->guardRequest('POST', 'demo/records')->getStatusCode());
        }
        $this->assertSame(429, $this->guardRequest('POST', 'demo/records')->getStatusCode());
        $this->assertSame(200, $this->guardRequest('POST', 'demo/records', [], '192.0.2.2')->getStatusCode());
        $this->assertSame(200, $this->guardRequest('GET', 'demo/records')->getStatusCode());
    }

    public function test_real_dcat_lazy_form_round_trip_and_direct_url_restrictions(): void
    {
        $this->actingAs(Administrator::query()->where('username', 'demo')->firstOrFail(), config('admin.auth.guard'));
        $record = DemoRecord::query()->first();
        $rendered = $this->get('/admin/dcat-api/render?'.http_build_query([
            'renderable' => 'App_Admin_Forms_BalanceForm', 'id' => $record->id,
            'action' => 'balance', '_trans_' => 'demo',
        ]))->assertOk();
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$rendered->getContent());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $input = [];
        foreach ($document->getElementsByTagName('input') as $element) {
            if ($element->hasAttribute('name')) {
                $input[$element->getAttribute('name')] = $element->getAttribute('value');
            }
        }
        $this->assertArrayHasKey(Form::LAZY_PAYLOAD_NAME, $input);
        $input['amount'] = '0.20';
        // Dcat explicitly sends its JsonResponse within the controller.
        ob_start();
        try {
            $submitted = $this->postJson('/admin/dcat-api/form', $input);
        } finally {
            ob_end_clean();
        }
        $submitted->assertOk()->assertJsonPath('status', true);
        $this->assertSame(number_format((float) $record->balance + 0.2, 2, '.', ''), $record->fresh()->balance);
        $this->assertSame(1, DemoLog::query()->where('action', 'balance')->count());
        $this->get('/admin/auth/users')->assertForbidden();
        $this->postJson('/admin/dcat-api/form/upload', ['_form_' => 'App\\Admin\\Forms\\BalanceForm'])->assertForbidden();
        $this->get('/admin/dcat-api/render?renderable=Dcat_Admin_Grid_LazyRenderable')->assertForbidden();
    }

    public function test_csrf_is_enforced_for_public_login_and_dcat_writes(): void
    {
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends ValidateCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
        $this->post('/demo/enter', ['username' => 'demo'])->assertStatus(419);
        $this->assertNull(Admin::guard()->user());
        $token = bin2hex(random_bytes(20));
        $this->withSession(['_token' => $token])->post('/demo/enter', ['username' => 'demo', '_token' => $token])->assertRedirect(admin_url('demo/overview'));
        $this->assertSame('demo', Admin::guard()->user()->username);
        $count = DemoLog::query()->count();
        $this->postJson('/admin/dcat-api/form', ['_form_' => 'App\\Admin\\Forms\\BulkNoticeForm', 'action' => 'notice', 'content' => '不能落库'])->assertStatus(419);
        $this->postJson('/admin/demo/records', ['title' => '不能落库', 'code' => 'NO_CSRF'])->assertStatus(419);
        $this->assertSame($count, DemoLog::query()->count());
        $this->assertFalse(DemoRecord::query()->where('code', 'NO_CSRF')->exists());
    }

    private function demoLogin(): void
    {
        Admin::guard()->setUser(Administrator::query()->where('username', 'demo')->firstOrFail());
    }

    private function guardRequest(string $method, string $path, array $input = [], string $ip = '192.0.2.1'): Response
    {
        $request = Request::create('/admin/'.$path, $method, $input, [], [], ['REMOTE_ADDR' => $ip]);
        $route = new RoutingRoute([$method], 'admin/'.$path, fn () => null);
        if (str_starts_with($path, 'dcat-api/')) {
            $route->name(admin_api_route_name(substr($path, strlen('dcat-api/'))));
        } elseif ($path === 'kit/locale') {
            $route->name(admin_route_name('kit.locale'));
        }
        $request->setRouteResolver(fn () => $route);

        return app(DemoAccess::class)->handle($request, fn (Request $request) => response()->json($request->all()));
    }

    private function assertGuardRejected(string $method, string $path, array $input, int $status): void
    {
        try {
            $this->guardRequest($method, $path, $input);
            $this->fail('Expected middleware rejection: '.$path);
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode(), $path);
        }
    }

    private function assertValidation(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Expected validation error for '.$field);
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}
