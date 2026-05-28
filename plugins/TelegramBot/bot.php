<?php
/**
 * Telegram Bot long-polling 入口（CLI 模式）。
 *
 * 用法：在主程序根目录运行
 *   php app/Plugin/TelegramBot/bot.php
 *
 * 也可以放到 supervisord：
 *   command=php /path/to/Acg-Faka-Local/app/Plugin/TelegramBot/bot.php
 *
 * 进程会持续 long-poll Telegram，不会主动退出。需要时用 Ctrl+C 或 supervisorctl 停止。
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    echo "This script must run from CLI.\n";
    exit(1);
}

date_default_timezone_set('Asia/Shanghai');
@set_time_limit(0);
@ini_set('memory_limit', '256M');

if (!defined('BASE_PATH')) {
    // 当前文件位于 app/Plugin/TelegramBot/bot.php，往上两级到主程序根
    define('BASE_PATH', dirname(__DIR__, 3) . DIRECTORY_SEPARATOR);
}

require BASE_PATH . 'vendor/autoload.php';
require BASE_PATH . 'kernel/Helper.php';

if (!defined('APP_VERSION')) {
    define('APP_VERSION', config('app')['version'] ?? '0.0.0');
}

// 启动 Eloquent
$capsule = new \Illuminate\Database\Capsule\Manager();
$capsule->addConnection(config('database'));
$capsule->setAsGlobal();
$capsule->bootEloquent();

use App\Plugin\TelegramBot\Library\Bot;
use App\Util\Plugin as PluginUtil;

// fake $_SERVER —— CLI 模式下主程序工具方法（Client::getUrl 等）会读 $_SERVER。
// 不 fake 的话 OrderService::trade() 内部会 fatal。
$shopUrl = (string)(\App\Model\Config::get('shop_url') ?: \App\Model\Config::get('callback_domain') ?: '');
if ($shopUrl !== '') {
    $parsed = parse_url($shopUrl);
    $_SERVER['HTTP_HOST']      = ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    $_SERVER['REQUEST_SCHEME'] = $parsed['scheme'] ?? 'http';
    $_SERVER['HTTPS']          = ($parsed['scheme'] ?? '') === 'https' ? 'on' : '';
} else {
    $_SERVER['HTTP_HOST']      = $_SERVER['HTTP_HOST']      ?? 'localhost';
    $_SERVER['REQUEST_SCHEME'] = $_SERVER['REQUEST_SCHEME'] ?? 'http';
    $_SERVER['HTTPS']          = $_SERVER['HTTPS']          ?? '';
}
$_SERVER['HTTP_USER_AGENT'] = 'TelegramBot/1.0';
$_SERVER['REMOTE_ADDR']     = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

echo "[" . date('Y-m-d H:i:s') . "] TelegramBot CLI 启动中...\n";

while (true) {
    try {
        $cfg = PluginUtil::getConfig('TelegramBot', false);
        if (empty($cfg['bot_token'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Bot Token 未配置，30 秒后重试\n";
            sleep(30);
            continue;
        }
        $bot = new Bot($cfg);
        $me = $bot->api()->getMe();
        if (!is_array($me) || empty($me['username'])) {
            echo "[" . date('Y-m-d H:i:s') . "] Bot Token 无效，30 秒后重试\n";
            sleep(30);
            continue;
        }
        echo "[" . date('Y-m-d H:i:s') . "] Bot @{$me['username']} 已连接，开始 long polling\n";
        $bot->runForever();
    } catch (\Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] 致命异常: " . $e->getMessage() . "\n";
        echo "[" . date('Y-m-d H:i:s') . "] 5 秒后重启\n";
        sleep(5);
    }
}
