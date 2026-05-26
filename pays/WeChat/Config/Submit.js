[
    {
        name: `${util.icon("/app/Pay/WeChat/Assets/Icon/Setting.png")} 基本配置(扫码支付)`,
        form: [
            {
                title: "商户号",
                name: "mch_id",
                type: "input",
                placeholder: "微信支付分配的商户号",
                required: true
            },
            {
                title: "AppID",
                name: "app_id",
                type: "input",
                placeholder: "微信平台分配给开发者的应用ID",
                required: true
            },
            {
                title: "API密钥",
                name: "key",
                type: "input",
                placeholder: "微信支付API密钥",
                required: true
            }
        ]
    },
    {
        name: `${util.icon("/app/Pay/WeChat/Assets/Icon/Js.png")} JS支付配置`,
        form: [
            {
                title: "收款方",
                name: "payee",
                type: "input",
                placeholder: "收银台中显示的收款方"
            },
            {
                title: "AppSecret",
                name: "app_secret",
                type: "input",
                placeholder: "AppSecret"
            },
            {
                title: "网页授权域名",
                name: "http_url",
                type: "input",
                placeholder: "网页授权域名,需要带https://"
            }
        ]
    },
    {
        name: `${util.icon("/app/Pay/WeChat/Assets/Icon/H5.png")} H5支付配置`,
        form: [
            {
                title: "H5域名",
                name: "wap_url",
                type: "input",
                placeholder: "H5网站URL地址，需要带https://"
            },
            {
                title: "H5网站名",
                name: "wap_name",
                type: "input",
                placeholder: "H5网站名"
            }
        ]
    },
]