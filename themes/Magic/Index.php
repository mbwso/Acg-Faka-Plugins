<?php require("Header.php"); ?>

    <div class=" notice">
        <?php echo $data['config']['notice']; ?>
    </div>

    <div class="layout">
        <label>选择商品分类</label>
        <div class="layout-select">
            <select class="category">
                <option value="">请选择分类</option>
            </select>
        </div>
    </div>
    <div class="layout">
        <label>选择商品</label>
        <div class="layout-select">
            <select class="commodity">
                <option value="">-</option>
            </select>
        </div>
    </div>

    <div class="layout commodity-di">
        <label>商品信息</label>
        <div class="layout-content __html">
            <form class="commodity-form">
                <p class="commodity_name"></p>
                <p class="description"></p>
                <p class="seckill">限时秒杀：<span class="seckill_timer"></span></p>
                <p><span class="price">0</span></p>
                <p>发货方式：<span class="delivery_box"><span class="delivery_way"></span><span class="stock">库存: ...</span></span></p>
                <p class="general race-view">宝贝类型：<span></span></p>
                <p class="general sku-view"></p>
                <p>联系方式：<input class="acg-input contact" type="text" name="contact" placeholder="请输入联系方式"></p>
                <p class="widget"></p>
                <p class="password">查询密码：<input class="acg-input" type="text" name="password" placeholder="请设置查询密码"></p>
                <p class="coupon">优惠代卷：<input class="acg-input" type="text" name="coupon" placeholder="没有可不填写"
                                              onchange="acg.API.tradeAmountPerform('.trade_amount')"></p>
                <p>购买数量：<input class="acg-input purchase_num" type="number" name="num" value="1"
                               onchange="acg.API.tradeAmountPerform('.trade_amount')"> </p>
                <p class="captcha_status">人机验证：<input class="acg-input captcha-input" name="captcha" type="text" placeholder="请输入验证码"> <img
                            class="captcha"></p>
                <p class="purchase_count"></p>
                <p class="lot"></p>
                <p class="draft_status"></p>
            </form>
        </div>
    </div>

    <div class="layout pay-content">
        <label>付款</label>
        <div class="pay_list">
        </div>
    </div>
    </div>


    <script>
        acg.ready("<?php echo $data['from'];?>", () => {
            let __html = $('.__html').html();

            let __htmlInit = () => {
                $('.commodity-di').show();
                $('.__html').html(__html);
            }
            let __htmlUnload = () => {
                $('.commodity-di').hide();
                $('.__html').html("");
            }

            __htmlUnload();

            let defaultCategory = "<?php echo $data["categoryId"];?>";
            let defaultCommodity = "<?php echo $data["commodityId"];?>";
            let dom = {
                initCategory() {
                    acg.API.category({
                        success: res => {
                            $('.category').append('<option value="' + res.id + '">' + res.name + '</option>');
                        },
                        empty: () => {
                            $('.category').html('<option value="">没有分类</option>');
                        },
                        yes: () => {
                            if (defaultCategory && defaultCategory != 0) {
                                $('.category').val(defaultCategory);
                                this.commoditys(defaultCategory);
                                defaultCategory = null;
                            }
                        }
                    });
                },
                commoditys(categoryId) {
                    __htmlUnload();
                    $('.pay-content').hide();
                    $('.commodity').html('<option value="">请选择商品</option>');
                    acg.API.commoditys({
                        categoryId: categoryId,
                        success: item => {
                            $('.commodity').append('<option value="' + item.id + '">' + item.name + '</option>');
                        },
                        empty: () => {
                            $('.commodity').html('<option value="">该分类下没有商品</option>');
                        },
                        yes: () => {
                            if (defaultCommodity) {
                                $('.commodity').val(defaultCommodity);
                                this.commodity(defaultCommodity);
                                defaultCommodity = null;
                            }
                        }
                    });
                },
                commodity(commodityId) {
                    __htmlUnload();
                    $('.pay-content').hide();
                    acg.API.commodity({
                        commodityId: commodityId,
                        pay: ".pay-content",
                        auto: {
                            race: '.race-view',
                            name: '.commodity_name',
                            description: '.description',
                            delivery_way: '.delivery_way',
                            contact_type: '.contact',
                            coupon: '.coupon',
                            purchase_num: '.purchase_num',
                            captcha: '.captcha',
                            password_status: '.password',
                            lot_status: '.lot',
                            seckill_status: '.seckill',
                            card: '.card_count',
                            purchase_count: '.purchase_count',
                            price: '.price',
                            draft_status: '.draft_status',
                            widget: '.widget',
                            sku: '.sku-view'
                        },
                        begin: () => {
                            __htmlInit();
                        }
                    });
                }
            }

            dom.initCategory();

            $('.category').change(function () {
                dom.commoditys(this.value);
            });

            $('.commodity').change(function () {
                dom.commodity(this.value);
            });

            //初始化支付
            acg.API.pay({
                success: item => {
                    if (item.handle === "#system") {
                        <?php if ($data['user']){?>
                        $('.pay_list').append(' <a class="pay-button" onclick="acg.API.tradePerform(' + item.id + ')" style="line-height: 22px;color: #f5a6d9;font-weight: bold;"> <img src="' + item.icon + '"> ' + item.name + '(<?php echo sprintf("%.2f", $data['user']['balance'])?>)</a>');
                        <?php }?>
                    } else {
                        $('.pay_list').append(' <a class="pay-button" onclick="acg.API.tradePerform(' + item.id + ')" style="line-height: 22px;"><img src="' + item.icon + '"> ' + item.name + '</a>');
                    }
                }
            });
        });
    </script>
<?php require("Footer.php"); ?>