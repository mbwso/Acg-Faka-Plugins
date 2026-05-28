<?php
declare(strict_types=1);

namespace App\Plugin\TelegramBot\Library\Module;

use App\Model\User as ShopUser;
use App\Plugin\TelegramBot\Library\Renderer;
use App\Plugin\TelegramBot\Library\State;
use App\Plugin\TelegramBot\Library\TelegramApi;
use App\Service\UserSSO as UserSSOService;
use App\Util\Client;
use App\Util\Date;
use App\Util\Str;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Container\Di;

/**
 * 注册、绑定、解绑、一键登录 web 端。
 */
class Account
{
    private TelegramApi $api;
    private array $config;

    public function __construct(TelegramApi $api, array $config)
    {
        $this->api = $api;
        $this->config = $config;
    }

    public function startBind(array $tgUser): void
    {
        if ((int)($tgUser['user_id'] ?? 0) > 0) {
            $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 您已绑定账号，无需重复绑定。');
            return;
        }
        State::setState((int)$tgUser['tg_user_id'], 'bind:username');
        $this->api->sendMessage($tgUser['tg_chat_id'], '请输入您的账号，直接通过消息发送给我');
    }

    public function startRegister(array $tgUser): void
    {
        if ((int)($tgUser['user_id'] ?? 0) > 0) {
            $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 您已绑定账号，无需注册。');
            return;
        }
        State::setState((int)$tgUser['tg_user_id'], 'register:username');
        $this->api->sendMessage($tgUser['tg_chat_id'], '欢迎注册！请直接发送您想注册的用户名（6 位以上）');
    }

    /** 处理状态机里的文本（register / bind 系列） */
    public function handleText(array $tgUser, string $text): bool
    {
        $state = (string)($tgUser['state'] ?? '');
        if (!str_starts_with($state, 'register:') && !str_starts_with($state, 'bind:')) {
            return false;
        }
        $text = trim($text);

        if ($state === 'bind:username') {
            $data = ['username' => $text];
            State::setState((int)$tgUser['tg_user_id'], 'bind:password', $data);
            $this->api->sendMessage($tgUser['tg_chat_id'], '收到。请输入密码：');
            return true;
        }
        if ($state === 'bind:password') {
            $data = State::getStateData($tgUser);
            $username = (string)($data['username'] ?? '');
            State::setState((int)$tgUser['tg_user_id'], null);
            $user = ShopUser::query()->where('username', $username)
                ->orWhere('email', $username)
                ->orWhere('phone', $username)
                ->first();
            if (!$user) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '❌ 用户不存在');
                return true;
            }
            if (Str::generatePassword($text, $user->salt) !== $user->password) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '❌ 密码错误');
                return true;
            }
            if ((int)$user->status !== 1) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '❌ 该账号已被封禁');
                return true;
            }
            State::bindUser((int)$tgUser['tg_user_id'], (int)$user->id);
            $this->api->sendMessage($tgUser['tg_chat_id'], "✅ 绑定成功！欢迎 <b>{$user->username}</b>，发送 /start 重载菜单。");
            return true;
        }

        if ($state === 'register:username') {
            if (mb_strlen($text) < 4) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 用户名至少 4 位，请重新输入');
                return true;
            }
            if (ShopUser::query()->where('username', $text)->first()) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 该用户名已存在，换一个吧');
                return true;
            }
            State::setState((int)$tgUser['tg_user_id'], 'register:password', ['username' => $text]);
            $this->api->sendMessage($tgUser['tg_chat_id'], '请设置登录密码（6 位以上）');
            return true;
        }
        if ($state === 'register:password') {
            if (mb_strlen($text) < 6) {
                $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 密码至少 6 位');
                return true;
            }
            $data = State::getStateData($tgUser);
            $username = (string)($data['username'] ?? '');

            $user = new ShopUser();
            $user->username = $username;
            $user->salt = Str::generateRandStr();
            $user->password = Str::generatePassword($text, $user->salt);
            $user->app_key = strtoupper(Str::generateRandStr(16));
            $user->create_time = Date::current();
            $user->status = 1;
            $user->avatar = '/favicon.ico';
            // 用 TG 昵称作为初始姓名
            $user->nicename = trim((string)($tgUser['first_name'] ?? '') . ' ' . (string)($tgUser['last_name'] ?? ''));
            $user->save();

            State::bindUser((int)$tgUser['tg_user_id'], (int)$user->id);
            State::setState((int)$tgUser['tg_user_id'], null);
            $this->api->sendMessage($tgUser['tg_chat_id'], "✅ 注册并自动绑定成功！欢迎 <b>{$user->username}</b>，发送 /start 重载菜单。");
            return true;
        }

        return false;
    }

    public function unbind(array $tgUser): void
    {
        if ((int)($tgUser['user_id'] ?? 0) <= 0) {
            $this->api->sendMessage($tgUser['tg_chat_id'], '⚠️ 您尚未绑定账号');
            return;
        }
        State::unbindUser((int)$tgUser['tg_user_id']);
        $this->api->sendMessage($tgUser['tg_chat_id'], '✅ 已解绑账号。发送 /start 重载菜单。');
    }

    /** 生成一次性 web 登录链接 */
    public function generateWebLoginUrl(array $tgUser): ?string
    {
        $uid = (int)($tgUser['user_id'] ?? 0);
        if ($uid <= 0) return null;

        $token = bin2hex(random_bytes(24));
        DB::table('plugin_telegrambot_sso')->insert([
            'token'       => $token,
            'user_id'     => $uid,
            'tg_user_id'  => (int)$tgUser['tg_user_id'],
            'expire_at'   => time() + 600, // 10 分钟有效
            'create_time' => Date::current(),
        ]);

        // 站点域名
        $base = (string)Client::getUrl();
        if ($base === '') {
            $base = rtrim((string)\App\Model\Config::get('shop_url'), '/');
        }
        return rtrim($base, '/') . '/plugin/TelegramBot/sso/login?token=' . $token;
    }
}
