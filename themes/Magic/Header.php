<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <title><?php echo $data["config"]["title"]; ?></title>
    <meta name="keywords" content="<?php echo $data["config"]["keywords"]; ?>"/>
    <meta name="description" content="<?php echo $data["config"]["description"]; ?>"/>
    <link href="<?php echo $data['favicon']; ?>" rel="icon">
    <?php
    echo css([
            "/app/View/User/Theme/Magic/Assets/Css/Style.css",
            "/assets/static/font/font-awesome-4.7.0/css/font-awesome.min.css"
    ]);

    echo js([
            "/assets/static/jquery.min.js",
            "/assets/static/acg.js"
    ]);
    ?>

    <!--start::HOOK-->
    <?php hook(\App\Consts\Hook::USER_VIEW_INDEX_HEADER); ?>
    <!--end::HOOK-->
</head>
<body>
<div class="card main-window">
    <div class="logo">
        <a href="<?php echo $data['user'] ? '/user/dashboard/index' : '/user/authentication/login?goto=/'; ?>"><img
                    src="<?php echo $data['user'] ? ($data['user']['avatar'] ? $data['user']['avatar'] : $data['favicon']) : $data['favicon']; ?>"></a>
        <div class="username">
            <?php if (!$data['user']) { ?>
                <a href="/user/authentication/login?goto=/"><i class="fa fa-sign-in"></i> 登录</a> · <a
                        href="/user/authentication/register?goto=/"><i class="fa fa-user-plus"></i> 注册</a>
            <?php } else { ?>
                <a href="/user/dashboard/index">欢迎你，<span
                            class="nickname"><?php echo $data['user']['username']; ?>(余额:<?php echo $data['user']['balance']; ?>)</span></a>
            <?php } ?>
            · <a
                    href="<?php echo getLocalRouter() == '/user/index/query' ? '/' : '/user/index/query'; ?>"> <?php echo getLocalRouter() == '/user/index/query' ? '<i class="fa fa-shopping-cart"></i> 购买商品' : '<i class="fa fa-safari"></i> 订单查询'; ?></a> <?php if ($data['config']['service_url']) { ?>·
            <a href="<?php echo $data['config']['service_url']; ?>" target="_blank"><i
                            class="fa fa-paint-brush"></i> 联系客服</a><?php } ?>

            <?php foreach (hook(\App\Consts\Hook::USER_VIEW_HEADER_NAV) as $item) { ?>
                · <a href="<?php echo $item['url']; ?>" target="<?php echo $item['target']; ?>"><i
                            class="<?php echo $item['icon']; ?>"></i> <?php echo $item['name']; ?></a>
            <?php } ?>
        </div>
    </div>