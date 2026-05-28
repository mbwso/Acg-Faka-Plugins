<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Controller;

use App\Controller\Base\View\UserPlugin;
use App\Model\User as ShopUser;
use App\Service\UserSSO as UserSSOService;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Annotation\Inject;

/**
 * URL: /plugin/telegrambot/sso/login?token=xxx
 * Bot 生成的一次性登录链接 → 设置登录 cookie → 跳转用户中心。
 */
class Sso extends UserPlugin
{
    #[Inject]
    private UserSSOService $sso;

    public function login(): string
    {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '') {
            return $this->errorPage('登录令牌缺失');
        }

        $row = DB::table('plugin_telegrambot_sso')->where('token', $token)->first();
        if (!$row) {
            return $this->errorPage('令牌无效或已使用');
        }
        if ((int)$row->used === 1) {
            return $this->errorPage('该令牌已经使用过，请到 Bot 重新生成');
        }
        if ((int)$row->expire_at < time()) {
            return $this->errorPage('令牌已过期，请到 Bot 重新生成');
        }

        $user = ShopUser::query()->find((int)$row->user_id);
        if (!$user) {
            return $this->errorPage('账号不存在');
        }
        if ((int)$user->status !== 1) {
            return $this->errorPage('账号已被封禁');
        }

        DB::table('plugin_telegrambot_sso')->where('token', $token)->update(['used' => 1]);
        $this->sso->loginSuccess($user);

        // 跳到会员中心
        header('Location: /user/personal');
        return '正在跳转...';
    }

    private function errorPage(string $msg): string
    {
        return <<<HTML
<!doctype html>
<html lang="zh-CN"><head><meta charset="utf-8"><title>登录失败</title>
<style>body{font-family:-apple-system,Helvetica,Arial,sans-serif;background:#f7f7fa;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.card{background:#fff;padding:32px 40px;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.08);max-width:420px;text-align:center}
h1{font-size:20px;margin:0 0 12px;color:#e74c3c}p{color:#555;margin:8px 0}a{color:#3498db;text-decoration:none}</style></head>
<body><div class="card"><h1>⚠️ 登录失败</h1><p>{$msg}</p><p><a href="/">返回首页</a></p></div></body></html>
HTML;
    }
}
