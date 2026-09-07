<?php

namespace App\Console\Commands;

use App\Services\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoInstall extends Command
{
    protected $signature = 'demo:install';

    protected $description = '幂等安装本地 SQLite 演示、受限身份和扩展资源';

    public function handle(DemoData $data): int
    {
        $connection = DB::connection();
        $adminConnection = DB::connection(config('admin.database.connection'));
        if ($connection->getDriverName() !== 'sqlite' || $adminConnection->getDriverName() !== 'sqlite'
            || $connection->getDatabaseName() !== $adminConnection->getDatabaseName()) {
            $this->error('demo:install 仅支持同一个本地 SQLite 演示数据库。');

            return self::FAILURE;
        }

        // Use the package migrations directly; admin:install also generates app/Admin
        // and its default seeder creates admin/admin and truncates permissions.
        if ($this->call('migrate', [
            '--path' => ['vendor/woodynew/dcat-laravel-admin/database/migrations', 'database/migrations'],
            '--force' => true,
        ]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        $data->installData();
        foreach (['dcat-admin-config', 'dcat-admin-assets', 'dcat-admin-lang', 'iframe-tab', 'iframe-tab.config'] as $tag) {
            if ($this->call('vendor:publish', ['--tag' => $tag]) !== self::SUCCESS) {
                return self::FAILURE;
            }
        }
        if ($this->call('dcat-admin-kit:install') !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('演示安装完成。已有业务记录和应用配置已保留，通过首页按钮进入 demo 账户。');

        return self::SUCCESS;
    }
}
