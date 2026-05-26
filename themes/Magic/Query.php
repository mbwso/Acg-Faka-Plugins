<?php require("Header.php"); ?>


    <div class="layout">
        <div class="layout-search">
            <input type="text" placeholder="请输入联系方式或订单号进行查询" class="keywords" value="<?php echo $data['tradeNo']; ?>">
            <button type="button" class="query-btn">查询</button>
        </div>
    </div>


    <div class="layout">
        <div class="layout-result">
            <h1 class="notfound">未查询到相关订单</h1>
            <div class="order-success">
            </div>
        </div>
    </div>

    </div>
    <script>
        acg.ready("<?php echo $from;?>", () => {
            let instance = $('.keywords');

            function query(keywords) {
                let orderSuccess = $('.order-success');
                $('.notfound').hide();
                orderSuccess.hide();
                orderSuccess.html('');
                acg.API.query({
                    keywords: keywords,
                    success: order => {
                        if (order.commodity && order.pay) {
                            let status = '<span style="color: red;">未支付</span>';
                            if (order.status === 1) {
                                status = '<span style="color: green;">已支付</span>';
                            }

                            let race = "";
                            if (order.race) {
                                race = " ( <b style='color: #20b033;'>" + order.race + "</b> )";
                            }

                            let html = '<div class="hr-top">\n' +
                                '                        <div style="font-size: 14px;">订单号：<b class="trade_no">' + order.trade_no + '</b> <?php hook(\App\Consts\Hook::USER_VIEW_QUERY_TRADE_NO);?></div>\n' +
                                '                        <div style="font-size: 14px;">下单金额：<b style="color: red;" class="amount">' + order.amount + '</b></div>\n' +
                                '                        <div style="font-size: 14px;">购买数量：<b style="color: #8e8ef3;" class="buyNum">' + order.card_num + '</b></div>\n' +
                                '                        <div style="font-size: 14px;">下单时间：<b class="create_time">' + order.create_time + '</b></div>\n' +
                                '                        <div style="font-size: 14px;">商品名称：<b>' + order.commodity.name + race + '</b></div>\n' +
                                '                        <div style="font-size: 14px;">支付方式：<b class="icon"><img src="' + order.pay.icon + '" height="16px" style="position: relative;top: 2px;">' + order.pay.name + '</b></div>\n' +
                                '                        <div style="font-size: 14px;">订单状态：<b class="status">' + status + '</b></div>\n' +
                                '                        <div style="font-size: 14px;' + (order.status == 1 ? '' : 'display: none;') + '" class="payDateView">支付时间：<b class="pay_date"\n' +
                                '                                                                                                style="color: green;">' + order.pay_time + '</b>\n' +
                                '                        </div>\n' +
                                '                        <div style="font-size: 14px;' + (order.leave_message ? "" : " display: none;") + '">使用说明：<b>' + order.leave_message + '</b></div>\n' +
                                '                        <div style="font-size: 14px;margin: 5px 0 5px 0;">卡密信息：<input style="' + (order.commodity.password_status == 1 ? '<?php echo $data['user'] ? 'display:none;' : '';?>' : 'display:none;') + '"  type="text" placeholder="请输入查询密码.." class="query-password passId-' + order.id + '"> <b class="getCard" data-id="' + order.id + '">查看</b></div>\n' +
                                '                        <div style="margin-top: 10px; display: none;" class="cardInfoView-' + order.id + '">\n' +
                                '                            <textarea class="card-textarea cardInfo-' + order.id + '" style="height: 420px;"></textarea>\n' +
                                '                        </div>\n' +
                                '                    </div>';
                            orderSuccess.append(html);
                        }
                    },
                    yes: res => {
                        orderSuccess.show();
                        $('.getCard').click(function () {
                            let orderId = $(this).attr('data-id');
                            acg.API.secret({
                                orderId: orderId,
                                password: $('.passId-' + orderId).val(),
                                success: res => {
                                    let secret = "";
                                    if (res.widget) {
                                        secret += "--------------您隐私内容---------------\n";
                                        for (const widgetKey in res.widget) {
                                            secret += res.widget[widgetKey].cn + "：" + res.widget[widgetKey].value + "\n";
                                        }
                                        secret += "--------------卡密信息---------------\n";
                                    }
                                    secret += res.secret;
                                    $('.cardInfo-' + orderId).html(secret);
                                    $('.cardInfoView-' + orderId).show(80);
                                }
                            });
                        });
                    },
                    error: () => {
                        $('.notfound').show();
                    }
                });
            }

            $('.query-btn').click(function () {
                query(instance.val());
            });

            <?php if ($data['tradeNo']) {?>
            $('.query-btn').click();
            <?php } elseif ($data['user']){?>
            query("");
            <?php }?>

        });
    </script>
<?php require("Footer.php"); ?>