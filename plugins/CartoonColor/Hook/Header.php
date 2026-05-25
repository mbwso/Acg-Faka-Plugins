<?php
declare(strict_types=1);

namespace App\Plugin\CartoonColor\Hook;

use App\Controller\Base\View\ManagePlugin;
use Kernel\Annotation\Hook;

class Header extends ManagePlugin
{
    private function common(&$css)
    {
        //按钮阴影颜色
        $click_shadow = getPluginConfig('CartoonColor')['common_click_shadow'] ?? '';
        if (!empty($click_shadow)) {
            $css[] = <<<html
.button-click {
    -webkit-box-shadow: 0 1px 4px 0 $click_shadow;
    box-shadow: 0 1px 4px 0 $click_shadow;
}
html;
        }
//        //弹出框背景
//        $layer = getPluginConfig('CartoonColor')['common_layer'] ?? '';
//        if (!empty($layer)) {
//            $css[] = <<<html
//.layui-layer {
//    box-shadow: $layer 0px 7px 29px 0px;
//    background: -moz-linear-gradient(top, $layer 0%, #ffffff 100%);
//    background: -webkit-gradient(linear, left top, left bottom, color-stop(0%, $layer), color-stop(100%, #ffffff));
//    background: -webkit-linear-gradient(top, $layer 0%, #ffffff 100%);
//    background: -o-linear-gradient(top, $layer 0%, #ffffff 100%);
//    background: -ms-linear-gradient(top, $layer 0%, #ffffff 100%);
//    background: linear-gradient(to bottom, $layer 0%, #ffffff 100%);
//}
//html;
//        }
//        //弹出框文字
//        $layer_text = getPluginConfig('CartoonColor')['common_layer_text'] ?? '';
//        if (!empty($layer_text)) {
//            $css[] = <<<html
//.layui-layer-dialog .layui-layer-content {
//    color: $layer_text;
//}
//html;
//        }

    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_INDEX_HEADER)]
    public function index()
    {
        $css = [];
        $this->common($css);

        //导航阴影颜色
        $nav_shadow = getPluginConfig('CartoonColor')['index_nav_shadow'] ?? '';
        if (!empty($nav_shadow)) {
            $css[] = <<<html
.navbar {
    -webkit-box-shadow: 0 6px 0 0 rgb(0 0 0 / 1%), 0 15px 32px 0 $nav_shadow;
    box-shadow: 0 6px 0 0 rgb(0 0 0 / 1%), 0 15px 32px 0 $nav_shadow;
}
html;
        }
        //卡片阴影颜色
        $card_shadow = getPluginConfig('CartoonColor')['index_card_shadow'] ?? '';
        if (!empty($card_shadow)) {
            $css[] = <<<html
.card {
    -webkit-box-shadow: 0 6px 0 0 rgb(0 0 0 / 1%), 0 15px 32px 0 $card_shadow;
    box-shadow: 0 6px 0 0 rgb(0 0 0 / 1%), 0 15px 32px 0 $card_shadow;
}
html;
        }

        $css_res = implode("\n", $css);

        echo '<style>'.$css_res.'</style>';
    }

    #[Hook(point: \App\Consts\Hook::USER_VIEW_HEADER)]
    public function user()
    {
        $css = [];
        $this->common($css);
        //导航栏背景颜色
        $user_nav = getPluginConfig('CartoonColor')['user_nav'] ?? '';
        if (!empty($user_nav)) {
            $css[] = <<<html
.fly-header {
    background: initial;
    background-color: $user_nav;
}
html;
        }
        //滚动条颜色
        $user_scrollbar = getPluginConfig('CartoonColor')['user_scrollbar'] ?? '';
        if (!empty($user_scrollbar)) {
            $css[] = <<<html
::-webkit-scrollbar-thumb {
    background: $user_scrollbar;
}
html;
        }
        //导航栏文字
        $user_nav_text = getPluginConfig('CartoonColor')['user_nav_text'] ?? '';
        if (!empty($user_nav_text)) {
            $css[] = <<<html
.fly-header .fly-nav a {
    color: $user_nav_text;
}
html;
        }
        //导航栏文字悬停
        $user_nav_text_hover = getPluginConfig('CartoonColor')['user_nav_text_hover'] ?? '';
        if (!empty($user_nav_text_hover)) {
            $css[] = <<<html
.fly-header .fly-nav a:hover {
    color: $user_nav_text_hover;
}
html;
        }
        //侧边菜单文字
        $user_side_text = getPluginConfig('CartoonColor')['user_side_text'] ?? '';
        if (!empty($user_side_text)) {
            $css[] = <<<html
.layui-nav .layui-nav-item a {
    color: $user_side_text;
}
html;
        }
        //侧边菜单文字悬停
        $user_side_text_hover = getPluginConfig('CartoonColor')['user_side_text_hover'] ?? '';
        if (!empty($user_side_text_hover)) {
            $css[] = <<<html
.layui-nav-tree > .layui-nav-item > a:hover
    color: $user_side_text_hover !important;
}
html;
        }
        //表单标题
        $user_title = getPluginConfig('CartoonColor')['user_title'] ?? '';
        if (!empty($user_title)) {
            $css[] = <<<html
.content-header {
    color: $user_title;
}
.layui-tab-title li a {
    color: $user_title !important;
}
.layui-tab-brief > .layui-tab-title .layui-this:after{
    border-bottom: 2px solid $user_title !important;
}
html;
        }

        $css_res = implode("\n", $css);

        echo '<style>'.$css_res.'</style>';
    }
}
