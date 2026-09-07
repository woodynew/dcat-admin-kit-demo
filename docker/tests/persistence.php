<?php

// Run only inside this demo container against its own /data volume.
use App\Models\DemoRecord;
use App\Services\DemoData;
use Dcat\Admin\Models\Administrator;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Contracts\Console\Kernel;

require '/www/vendor/autoload.php';
$app = require '/www/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.connections.sqlite.database') !== '/data/database.sqlite') {
    throw new RuntimeException('Unexpected database target.');
}

$snapshotFile = '/data/docker-smoke.json';
$cookieFile = '/data/docker-smoke-cookies.json';
$cookies = new FileCookieJar($cookieFile, true);
$http = new Client(['base_uri' => 'http://nginx', 'cookies' => $cookies, 'allow_redirects' => false, 'timeout' => 10]);
$record = DemoRecord::where('code', 'DOCKER_SMOKE')->first();
$snapshot = static fn ($record): array => [
    'key_hash' => hash('sha256', config('app.key')),
    'record_id' => $record->id,
    'title' => $record->title,
    'demo_password_hash' => hash('sha256', Administrator::where('username', 'demo')->value('password')),
];

if (($argv[1] ?? '') === 'seed') {
    $page = (string) $http->get('/')->getBody();
    if (! preg_match('/name="_token"[^>]*value="([^"]+)"/', $page, $match)) {
        throw new RuntimeException('Login CSRF token missing.');
    }
    if ($http->post('/demo/enter', ['form_params' => ['_token' => $match[1]]])->getStatusCode() !== 302) {
        throw new RuntimeException('Demo login failed.');
    }
    $cookies->save($cookieFile);
    $record ??= app(DemoData::class)->create(['code' => 'DOCKER_SMOKE', 'title' => '重建后保留的演示数据']);
    file_put_contents($snapshotFile, json_encode($snapshot($record), JSON_THROW_ON_ERROR));
    echo "Persistence fixture created.\n";
} elseif (($argv[1] ?? '') === 'verify') {
    $expected = json_decode(file_get_contents($snapshotFile), true, 512, JSON_THROW_ON_ERROR);
    if (! $record || $snapshot($record) !== $expected) {
        throw new RuntimeException('Key, account or record changed across recreation.');
    }
    if ($http->get('/admin/demo/overview')->getStatusCode() !== 200) {
        throw new RuntimeException('Login session lost across recreation.');
    }
    echo "Key, account, login session and record persisted across recreation.\n";
} else {
    throw new InvalidArgumentException('Expected seed or verify.');
}
