<?php
declare (strict_types=1);

namespace App\Plugin\ContactVerify\Hook;

use Kernel\Annotation\Hook;
use Kernel\Exception\JSONException;

class Main
{
    /**
     * @throws JSONException
     */
    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_TRADE_BEGIN)]
    public function trade(): void
    {
        if (isset($_POST['confirm_contact']) && ($_POST['confirm_contact'] != $_POST['contact'])) {
            throw new JSONException("两次联系方式输入不一致");
        }
    }
}