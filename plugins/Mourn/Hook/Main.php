<?php
declare (strict_types=1);

namespace App\Plugin\Mourn\Hook;

use Kernel\Annotation\Hook;

class Main
{

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_HEADER)]
    public function style(): void
    {
        echo '<style>html { -webkit-filter: grayscale(100%); filter: progid:DXImageTransform.Microsoft.BasicImage(graysale=1); } </style>';
    }
}