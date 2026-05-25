<?php
declare(strict_types=1);

namespace App\Plugin\EmailNotification\Hook;


use App\Controller\Base\View\ManagePlugin;
use App\Model\Commodity;
use App\Model\Order;
use App\Model\Pay;
use App\Service\Email;
use Kernel\Annotation\Hook;
use Kernel\Annotation\Inject;

class Main extends ManagePlugin
{
    #[Inject]
    private Email $email;

    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_TRADE_AFTER)]
    public function trade(Commodity $commodity, Order $order, Pay $pay)
    {
        try {
            $config = getPluginConfig("EmailNotification");
            if ($config['trade'] == 1 && $pay->handle != "#system") {
                $this->email->send($config['email'], "【下单通知】有人在店铺下单啦", str_replace(["[contact]", "[card_num]", "[name]", "[pay]"], [$order->contact, $order->card_num, $commodity->name, $pay->name], $config['trade_content']));
            }
        } catch (\Error | \Exception $e) {
        }
    }

    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_PAY_AFTER)]
    public function pay(Commodity $commodity, Order $order, Pay $pay)
    {
        try {
            $config = getPluginConfig("EmailNotification");
            if ($config['payment'] == 1) {
                $this->email->send($config['email'], "【付款通知】店铺有人付款了", str_replace(["[contact]", "[card_num]", "[name]", "[pay]"], [$order->contact, $order->card_num, $commodity->name, $pay->name], $config['payment_content']));
            }
        } catch (\Error | \Exception $e) {
        }
    }
}