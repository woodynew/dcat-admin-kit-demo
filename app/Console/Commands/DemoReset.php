<?php

namespace App\Console\Commands;

use App\Services\DemoData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoReset extends Command
{
    protected $signature = 'demo:reset';

    protected $description = '使用共享写锁和事务重置演示记录与日志，保留后台身份和扩展';

    public function handle(DemoData $data): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('demo:reset 仅支持本地 SQLite 演示数据库。');

            return self::FAILURE;
        }
        $data->reset();
        $this->info('演示数据已重置。');

        return self::SUCCESS;
    }
}
