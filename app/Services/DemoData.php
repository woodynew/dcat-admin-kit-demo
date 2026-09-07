<?php

namespace App\Services;

use App\Models\DemoLog;
use App\Models\DemoRecord;
use App\Models\DemoState;
use Closure;
use Dcat\Admin\Models\Administrator;
use Dcat\Admin\Models\Menu;
use Dcat\Admin\Models\Permission;
use Dcat\Admin\Models\Role;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DemoData
{
    public const LOCK_KEY = 'dcat-demo:data-write';

    public const MAX_RECORDS = 200;

    public const MAX_LOGS = 500;

    public function installData(): void
    {
        $this->write(function () {
            $this->installIdentity();

            if (! DemoState::query()->whereKey('last_reset_at')->exists()) {
                $this->seedRecords();
            }
        });
    }

    public function reset(): void
    {
        $this->write(function () {
            // DELETE preserves the sequence: old open forms cannot target reused IDs.
            DemoLog::query()->delete();
            DemoRecord::query()->delete();
            DemoState::query()->delete();
            $this->seedRecords();
        });
    }

    public function create(array $data): DemoRecord
    {
        return $this->write(function () use ($data) {
            if (DemoRecord::query()->count() >= self::MAX_RECORDS) {
                $this->invalid('title', '共享演示最多保留 200 条记录，请删除记录或等待每小时重置。');
            }

            $record = DemoRecord::query()->create($this->validated($data));
            $this->appendLog('create', '创建演示记录：'.$record->code, $record->id);

            return $record->refresh();
        });
    }

    public function update(DemoRecord $record, array $data): DemoRecord
    {
        return $this->write(function () use ($record, $data) {
            $current = $this->current($record->getKey());
            foreach (['title', 'code', 'url', 'description', 'status', 'balance', 'updated_at'] as $field) {
                if ((string) $current->getRawOriginal($field) !== (string) $record->getRawOriginal($field)) {
                    $this->invalid('id', '记录已被其他访客修改，请刷新后重试。');
                }
            }
            $current->fill($this->validated($data, $current))->save();
            $this->appendLog('update', '更新演示记录：'.$current->code, $current->id);

            return $current->refresh();
        });
    }

    public function delete(DemoRecord $record): void
    {
        $this->write(function () use ($record) {
            $current = $this->current($record->getKey());
            if ((string) $current->getRawOriginal('updated_at') !== (string) $record->getRawOriginal('updated_at')) {
                $this->invalid('id', '记录已被其他访客修改，请刷新后重试。');
            }
            $current->delete();
            $this->appendLog('delete', '删除演示记录：'.$current->code, $current->id);
        });
    }

    public function log(string $action, string $message, ?int $recordId = null): void
    {
        $this->write(function () use ($action, $message, $recordId) {
            Validator::make(compact('action', 'message'), [
                'action' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_-]*$/'],
                'message' => ['required', 'string', 'max:2000'],
            ])->validate();
            if ($recordId !== null) {
                $this->current($recordId);
            }
            $this->appendLog($action, $message, $recordId);
        });
    }

    public function addBalance(int $id, string|float $amount): void
    {
        $this->write(function () use ($id, $amount) {
            Validator::make(['amount' => $amount], [
                'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:10000'],
            ])->validate();
            $record = $this->current($id);
            // Integer cents avoid floating point accumulation across repeated actions.
            $cents = (int) round((float) $record->balance * 100) + (int) round((float) $amount * 100);
            if ($cents > 999999999999) {
                $this->invalid('amount', '余额超过演示字段上限。');
            }
            $record->balance = sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
            $record->save();
            $this->appendLog('balance', '增加演示余额 '.number_format((float) $amount, 2, '.', ''), $id);
        });
    }

    public function notify(string $content): void
    {
        $this->log('notice', $content);
    }

    private function write(Closure $callback): mixed
    {
        try {
            return Cache::lock(self::LOCK_KEY, 120)->block(5, fn () => DB::transaction($callback));
        } catch (LockTimeoutException) {
            $this->invalid('demo', '共享演示正在重置或保存，请稍后重试。');
        }
    }

    private function current(?int $id): DemoRecord
    {
        $record = $id ? DemoRecord::query()->find($id) : null;
        if (! $record) {
            $this->invalid('id', '记录不存在或已被重置，请刷新列表。');
        }

        return $record;
    }

    private function validated(array $data, ?DemoRecord $record = null): array
    {
        return Validator::make($data, [
            'title' => [$record ? 'sometimes' : 'required', 'required', 'string', 'max:120'],
            'code' => [$record ? 'sometimes' : 'required', 'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('demo_records', 'code')->ignore($record?->id)],
            'url' => ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'],
            // Layer interprets textalert contents as HTML, so this field is plain text.
            'description' => ['sometimes', 'nullable', 'string', 'max:2000', 'not_regex:/[<>]/'],
            'status' => ['sometimes', 'boolean'],
            'balance' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ])->validate();
    }

    private function appendLog(string $action, string $message, ?int $recordId = null): void
    {
        DemoLog::query()->create(['demo_record_id' => $recordId, 'action' => $action, 'message' => $message]);
        $cutoff = DemoLog::query()->orderByDesc('id')->skip(self::MAX_LOGS)->value('id');
        if ($cutoff !== null) {
            DemoLog::query()->where('id', '<=', $cutoff)->delete();
        }
    }

    private function seedRecords(): void
    {
        $samples = [
            ['欢迎体验 Dcat Admin Kit', 'WELCOME', 'https://example.com/demo', '这是一份所有访客共享的演示数据，可以编辑、复制并查看日志。', true, '128.50'],
            ['中文与 Emoji：旅行计划 🌏', 'UNICODE', 'https://example.com/travel?city=shanghai&lang=zh', "支持中文、Emoji 和多行内容。\n第二行用于展示长文本弹窗。", true, '88.88'],
            ['包含引号的标题 "Hello" & 单引号', 'QUOTES', 'https://example.com/search?q=hello%20world&source=demo', '包含引号的普通文本，适合观察弹窗与复制行为。', false, '0.00'],
            ['<img src=x onerror=alert(1)> 转义展示', 'ESCAPED', 'https://example.com/escaped?text=%3Cscript%3E', '危险文本仅在标题的转义显示器中演示；本说明字段只保存纯文本。', true, '5.20'],
            ['空链接与空说明', 'EMPTY', null, null, false, '0.00'],
            ['长内容展示：'.str_repeat('多字段排版与末尾截断 ', 5), 'LONG', 'https://example.com/resources/'.str_repeat('long-segment/', 12).'detail', str_repeat('这是用于演示长文本预览和弹窗的安全说明。', 35), true, '9999.99'],
            ['待处理的样例记录', 'PENDING', 'https://example.org/tasks/7', '通过表单修改状态，所有页面读取相同记录。', false, '20.00'],
            ['余额操作示例', 'BALANCE', 'https://example.org/accounts/8', '充值仅修改演示余额，并在同一事务中记录操作日志。', true, '100.00'],
        ];

        foreach ($samples as [$title, $code, $url, $description, $status, $balance]) {
            DemoRecord::query()->firstOrCreate(compact('code'), compact('title', 'url', 'description', 'status', 'balance'));
        }
        DemoState::query()->updateOrCreate(['key' => 'last_reset_at'], ['value' => now()->toIso8601String()]);
        $this->appendLog('reset', '共享演示数据已初始化，下一次自动重置在每小时整点。');
    }

    private function installIdentity(): void
    {
        // Reserve the framework's special ID with a non-login system identity.
        if (! Administrator::query()->whereKey(Administrator::DEFAULT_ID)->exists()) {
            $reserved = new Administrator(['username' => 'demo-system-'.Str::lower(Str::random(12)), 'name' => '系统保留账户', 'password' => Hash::make(Str::random(64))]);
            $reserved->id = Administrator::DEFAULT_ID;
            $reserved->save();
        }
        $default = Administrator::query()->where('username', 'admin')->first();
        if ($default && Hash::check('admin', $default->password)) {
            $default->forceFill(['password' => Hash::make(Str::random(64)), 'remember_token' => null])->save();
        }

        if (! Role::query()->whereKey(Role::ADMINISTRATOR_ID)->exists()) {
            $reserved = new Role(['slug' => 'demo-system', 'name' => '系统保留角色']);
            $reserved->id = Role::ADMINISTRATOR_ID;
            $reserved->save();
        }
        $role = Role::query()->firstOrCreate(['slug' => 'demo'], ['name' => '演示访客']);
        $user = Administrator::query()->firstOrCreate(['username' => 'demo'], ['name' => '演示访客', 'password' => Hash::make(Str::random(64))]);
        if ((int) $user->id === Administrator::DEFAULT_ID || (int) $role->id === Role::ADMINISTRATOR_ID) {
            $this->invalid('demo', 'demo 身份占用了系统保留 ID，请使用新的演示数据库。');
        }
        $user->roles()->sync([$role->id]);
        $permission = Permission::query()->updateOrCreate(['slug' => 'demo'], [
            'name' => '演示功能', 'http_method' => [],
            'http_path' => ['demo/*', 'POST:dcat-api/form', 'GET:dcat-api/render'],
        ]);
        $role->permissions()->sync([$permission->id]);
        $menuIds = [];
        foreach (['overview' => '演示概览', 'columns' => '列显示器', 'actions' => '操作与工具', 'records' => '共享记录', 'widgets' => '组件展示', 'features' => '全局特性', 'tabs' => '多标签页'] as $route => $title) {
            $menu = Menu::query()->firstOrCreate(['uri' => 'demo/'.$route], ['title' => $title, 'parent_id' => 0, 'order' => count($menuIds) + 1, 'icon' => 'feather icon-grid']);
            $menuIds[] = $menu->id;
        }
        $role->menus()->sync($menuIds);
        $permission->menus()->sync($menuIds);
        (new Menu)->flushCache();
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
