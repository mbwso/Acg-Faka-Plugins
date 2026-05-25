<?php
declare (strict_types=1);

return [
    [
        "title" => "通讯密钥",
        "name" => "app_key",
        "type" => "input",
        "placeholder" => "挂监控的时候，请填写这个密钥",
        "default" => \App\Util\Str::generateRandStr()
    ],
    [
        "title" => "添加卡密接口说明",
        "name" => "explain1",
        "type" => "explain",
        "placeholder" => '调用方式POST/GET 地址为 域名/plugin/CardAPI/api/save <br>POST参数：
        所有参数应URL编码
        <table border="1">
        <th>参数</th>
        <th></th>
        <th>说明</th>
        <tr>
        <td>commodity_id</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td>商品ID，必填</td>
        </tr>
        <tr>
        <td>race</td>
        <td></td>
        <td>商品种类，如年卡月卡，非必填</td>
        </tr>
        <tr>
        <td>note</td>
        <td></td>
        <td>备注信息，非必填</td>
        </tr>
        <tr>
        <td>secret</td>
        <td></td>
        <td>卡密内容，多个卡密用换行符分割，必填</td>
        </tr>
        <tr>
        <td>unique</td>
        <td></td>
        <td>检查卡密是否重复，填1为进行检查，非必填</td>
        </tr>
        </table>
        返回值：
        <table border="1">
        <th>字段</th>
        <th></th>
        <th>说明</th>
        <tr>
        <td>code</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td>返回200为成功,其他结果为失败</td>
        </tr>
        <tr>
        <td>msg</td>
        <td></td>
        <td>导入结果</td>
        </tr>
        <tr>
        <td>date</td>
        <td></td>
        <td></td>
        </tr>
        </table>',
    ],
    [
        "title" => "查询数量接口说明",
        "name" => "explain1",
        "type" => "explain",
        "placeholder" => '调用方式GET/POST 地址为 域名/plugin/CardAPI/api/num <br>GET参数：
        <table border="1">
        <th>参数</th>
        <th></th>
        <th>说明</th>
        <tr>
        <td>commodity_id</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td>商品ID，必填</td>
        </tr>
        <tr>
        <td>race</td>
        <td></td>
        <td>商品种类，如年卡月卡，非必填</td>
        </tr>
        </table>
        返回值：
        <table border="1">
        <th>字段</th>
        <th></th>
        <th>说明</th>
        <tr>
        <td>code</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
        <td>返回200为成功,其他结果为失败</td>
        </tr>
        <tr>
        <td>num</td>
        <td></td>
        <td>库存数量，非卡密商品返回0</td>
        </tr>
        <tr>
        <td>race</td>
        <td></td>
        <td>商品种类，如年卡月卡</td>
        </tr>
        </table>',
    ],
    [
        "title" => "查询商品信息",
        "name" => "explain1",
        "type" => "explain",
        "placeholder" => '调用方式GET 地址为 域名/admin/api/commodity/data <br>官方接口无需传参需登录，自行解析结果',
    ]
];