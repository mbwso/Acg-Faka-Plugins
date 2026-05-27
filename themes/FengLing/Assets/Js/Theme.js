/* 风铃发卡 — 前台下单页交互 */
window.FengLing = (function ($) {
    'use strict';

    var listType = 'select';
    var $category, $commodity, $categoryButtons, $commodityButtons;
    var $formCard, $payCard, $empty, $description, $payList;
    var rawDescriptionHtml = '';

    function el(id) { return $(id); }

    function showEmpty() {
        $formCard.hide();
        $payCard.hide();
        $empty.show();
    }

    function showForm() {
        $empty.hide();
        $formCard.show();
    }

    function setSelectMode() {
        $category.show();
        $commodity.show();
        $categoryButtons.hide().empty();
        $commodityButtons.hide().empty();
    }

    function setButtonMode() {
        $category.hide();
        $commodity.hide();
        $categoryButtons.show().empty();
        $commodityButtons.show().empty();
    }

    function loadCategories(defaultCategoryId, onLoaded) {
        if (listType === 'button') {
            $categoryButtons.empty();
        } else {
            $category.html('<option value="">请选择分类</option>');
        }

        acg.API.category({
            success: function (item) {
                if (listType === 'button') {
                    $categoryButtons.append(
                        $('<span class="fl-btn-item" data-id="' + item.id + '"></span>').text(item.name)
                    );
                } else {
                    $category.append('<option value="' + item.id + '">' + escapeAttr(item.name) + '</option>');
                }
            },
            empty: function () {
                if (listType === 'button') {
                    $categoryButtons.html('<span class="fl-empty-text">暂无分类</span>');
                } else {
                    $category.html('<option value="">暂无分类</option>');
                }
            },
            yes: function () {
                if (defaultCategoryId && defaultCategoryId !== '0') {
                    selectCategory(defaultCategoryId);
                }
                if (typeof onLoaded === 'function') onLoaded();
            }
        });
    }

    function selectCategory(categoryId) {
        if (listType === 'button') {
            $categoryButtons.find('.fl-btn-item').removeClass('is-active');
            $categoryButtons.find('[data-id="' + categoryId + '"]').addClass('is-active');
        } else {
            $category.val(categoryId);
        }
        loadCommodities(categoryId);
    }

    function loadCommodities(categoryId, defaultCommodityId) {
        $payCard.hide();
        $formCard.hide();

        if (listType === 'button') {
            $commodityButtons.empty();
        } else {
            $commodity.html('<option value="">请选择商品</option>');
        }

        acg.API.commoditys({
            categoryId: categoryId,
            success: function (item) {
                if (listType === 'button') {
                    $commodityButtons.append(
                        $('<span class="fl-btn-item" data-id="' + item.id + '"></span>').text(item.name)
                    );
                } else {
                    $commodity.append('<option value="' + item.id + '">' + escapeAttr(item.name) + '</option>');
                }
            },
            empty: function () {
                if (listType === 'button') {
                    $commodityButtons.html('<span class="fl-empty-text">该分类暂无商品</span>');
                } else {
                    $commodity.html('<option value="">该分类暂无商品</option>');
                }
                showEmpty();
            },
            yes: function () {
                if (defaultCommodityId) {
                    selectCommodity(defaultCommodityId);
                } else {
                    showEmpty();
                }
            }
        });
    }

    function selectCommodity(commodityId) {
        if (listType === 'button') {
            $commodityButtons.find('.fl-btn-item').removeClass('is-active');
            $commodityButtons.find('[data-id="' + commodityId + '"]').addClass('is-active');
        } else {
            $commodity.val(commodityId);
        }
        loadCommodity(commodityId);
    }

    function loadCommodity(commodityId) {
        $payCard.hide();
        $formCard.hide();

        acg.API.commodity({
            commodityId: commodityId,
            pay: '.pay-content',
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
                card: '.stock',
                purchase_count: '.purchase_count',
                price: '.price',
                draft_status: '.draft_status',
                widget: '.widget',
                sku: '.sku-view'
            },
            begin: function () {
                showForm();
            }
        });
    }

    function escapeAttr(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
            .replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function initPay(opts) {
        $payList.empty();
        acg.API.pay({
            success: function (item) {
                var icon = item.icon || '';
                var $a = $('<a class="pay-button"></a>')
                    .on('click', function () { acg.API.tradePerform(item.id); });
                $a.append('<img src="' + escapeAttr(icon) + '" alt="">');
                if (item.handle === '#system') {
                    if (!opts.user) return;
                    $a.append('<span><strong>' + escapeAttr(item.name) + '</strong><small>余额 ￥' + escapeAttr(opts.balance) + '</small></span>');
                } else {
                    $a.append('<span>' + escapeAttr(item.name) + '</span>');
                }
                $payList.append($a);
            }
        });
    }

    function bindBurger() {
        var $burger = $('.fl-topbar-burger');
        var $nav = $('.fl-topnav');
        $burger.on('click', function () { $nav.toggleClass('is-open'); });
    }

    return {
        init: function (opts) {
            opts = opts || {};
            listType = opts.listType === 'button' ? 'button' : 'select';

            $category = $('.category');
            $commodity = $('.commodity');
            $categoryButtons = $('.category-buttons');
            $commodityButtons = $('.commodity-buttons');
            $formCard = $('.commodity-form-card');
            $payCard = $('.pay-card');
            $empty = $('#fl-empty-state');
            $description = $('.description');
            $payList = $('.pay_list');

            if (listType === 'button') setButtonMode(); else setSelectMode();

            bindBurger();

            $category.on('change', function () {
                var v = $(this).val();
                if (v) loadCommodities(v); else showEmpty();
            });
            $commodity.on('change', function () {
                var v = $(this).val();
                if (v) loadCommodity(v); else showEmpty();
            });
            $categoryButtons.on('click', '.fl-btn-item', function () {
                selectCategory($(this).attr('data-id'));
            });
            $commodityButtons.on('click', '.fl-btn-item', function () {
                selectCommodity($(this).attr('data-id'));
            });

            loadCategories(opts.defaultCategory, function () {
                if (opts.defaultCategory && opts.defaultCommodity) {
                    loadCommodities(opts.defaultCategory, opts.defaultCommodity);
                }
            });

            initPay({ user: !!opts.user, balance: opts.balance || '0' });
        }
    };
}(jQuery));
