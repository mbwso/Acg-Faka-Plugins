<?php
declare(strict_types=1);

namespace App\Plugin\CommodityAPI\Hook;

use App\Model\Commodity;
use App\Model\Order;
use App\Model\User;
use App\Model\Pay;
use App\Util\Http;
use App\Util\Plugin;
use App\Util\Str;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Kernel\Annotation\Hook;

/**
 *
 */
class Main
{

    /**
     * 更新或安装时，安装数据库支持
     */
    private function InstallDB(): void
    {
        //判断字段是否存在，不存在则创建字段
        $textArray=array("api_headers","api_extend","api_url","api_key","api_identifier","api_success","api_success_text","api_failure_text");
        foreach ($textArray as $value){
            $extend = Manager::schema()->hasColumn("commodity", $value);
            if (!$extend) {
                Manager::schema()->table("commodity", function (Blueprint $blueprint) use ($value){
                    $blueprint->text($value)->nullable(true)->default(null);
                });
            }
        }
        
        $Array2=array("api_request_type","api_sign_type","api_num","api_success_type","api_failure_type");
        foreach ($Array2 as $value){
            $extend = Manager::schema()->hasColumn("commodity", $value);
            if (!$extend) {
                Manager::schema()->table("commodity", function (Blueprint $blueprint) use ($value){
                    $blueprint->String($value,10)->nullable(true)->default("0");
                });
            }
        }
    }
   #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::START)]
    public function State(): void
    {
        $this->InstallDB();
    }
    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::INSTALL)]
    public function Install(): void
    {
        $this->InstallDB();
    }

    #[\Kernel\Annotation\Plugin(state: \Kernel\Annotation\Plugin::UPGRADE)]
    public function Update(): void
    {
        $this->InstallDB();
    }
    
    #[Hook(point: \App\Consts\Hook::USER_API_ORDER_PAY_AFTER)]
    public function Notification(Commodity $commodity, Order $order, Pay $pay,int $i = 0,String $ret=""): void
    {
        $Plugin_Name="CommodityAPI";//插件的文件夹名称，用于存储日志
        try {
             //Plugin::log($Plugin_Name,"订单号:".$order->trade_no ." 商品id:".$order->commodity_id ." 第".(String)($i+1). "次通知结果".json_encode($order,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $request_type=(int)$commodity->api_request_type;
            $url=$commodity->api_url;
            $api_key=$commodity->api_key;
            $sign_type=(int)$commodity->api_sign_type;
            $identifier=$commodity->api_identifier;
            $success=$commodity->api_success;
            $num=(int)$commodity->api_num;
            $success_type=(int)$commodity->api_success_type;
            $success_text=$commodity->api_success_text;
            $failure_type=(int)$commodity->api_failure_type;
            $failure_text=$commodity->api_failure_text;
            $headers=$commodity->api_headers;
            $extend=$commodity->api_extend;
            if($request_type == 0 ){//选择不发送则跳过
                return;
            }
            if($i > $num){
                //超过重试次数还未成功，直接退出函数
                Plugin::log($Plugin_Name,"订单号:".$order->trade_no ." 商品id:".$order->commodity_id ." 未成功获取响应信息，不变更发货状态".$ret);
                $ret2=json_decode((string)$ret,true);
                if($failure_text !== ""){
                    if($failure_type == 1){
                        if (!empty($ret2)) {
                            $order->secret=$ret2[$failure_text];
                        }else{
                            $order->secret="未成功获取响应";
                        }
                        
                    }else{
                        $order->secret=$failure_text;
                    }
                   
                }
                $order->save(); 
                return;
            }
            $client = Http::make(['timeout' => 5, 'headers' => (array)json_decode((string)$headers, true)]);
            $data = [];
            $data['data'] = json_encode([
                "commodity" => $commodity->toArray(),
                "order" => $order->toArray(),
                "pay" => $pay->toArray()
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $param = json_decode((string)$extend, true);

            if (!empty($param)) {
                foreach ($param as $key => $val) {
                    $data[$key] = $val;
                }
            }
            $data['sign'] = Str::generateSignature($data, $api_key);
            if ((int)$request_type == 1) {//根据选择方式进行不同的发生请求
                $response = $client->post($url, [
                    "form_params" => $data
                ]);
            }else{
                $response = $client->get($url, [
                "query" => $data
            ]);
            }
                $return= json_decode($response->getBody()->getContents(),true);
                Plugin::log($Plugin_Name,"订单号:".$order->trade_no ." 商品id:".$order->commodity_id ." 第".(String)($i+1). "次通知结果".json_encode($return,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                //判断通知是否成功
                $isSuccess=false;
                if($identifier==""){
                   if(strpos(json_encode($return,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),$success) !== false){
                       $isSuccess=true;
                   }
                }else{
                    if($return[$identifier]==$success){
                        $isSuccess=true;
                    }
                }
                if($isSuccess){
                    Plugin::log($Plugin_Name,"订单号:".$order->trade_no ." 商品id:".$order->commodity_id ." 成功通知并成功获取响应信息，变更发货状态");
                    //通知结束，将订单改成已发货
                    if($success_text!==""){
                        if($success_type == 1){
                            $order->secret=$return[$success_text];
                        }else{
                            $order->secret=$success_text;
                        }
                    }
                    $order->delivery_status = 1;
                    $order->save(); 
                }else{
                     $this->Notification($commodity,$order,$pay,$i+1,json_encode($return,JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
                return;
        } catch (\Error | \Exception $e) {
            if($i < $num){
            Plugin::log($Plugin_Name,"订单号:".$order->trade_no ." 商品id:".$order->commodity_id ." 通知时返回状态码".(String)$e->getCode() ."，尝试重新通知");
            }
            $this->Notification($commodity,$order,$pay,$i+1,"");
        }
    }
    #[Hook(point: \App\Consts\Hook::ADMIN_VIEW_COMMODITY_POST)]
    public function CommodityPost(): void
    {
        echo '{title: "请求方式", name: "api_request_type", type: "select",dict:[{id:"0",name:"不发送"},{id:"1",name:"POST"},{id:"2",name:"GET"}],placeholder:"请选择",tips: "选择商品请求的方式", default: "0"},';
        echo '{title: "API地址", name: "api_url", type: "input",placeholder:"带http://或https://的地址",tips: "该商品使用的api", default: ""},';
        echo '{title: "通信密钥", name: "api_key", type: "input",placeholder:"",tips: "用作与签名的通讯密钥", default: ""},';
        echo '{title: "签名方式", name: "api_sign_type", type: "select",dict:[{id:"0",name:"MD5"}],placeholder:"请选择", default: "0"},';
        echo '{title: "签名说明", name: "api_explain2", type: "explain",placeholder:"签名算法详见/app/Util/Str.php类中的generateSignature方法。"},';
        echo '{title: "结果标识符", name: "api_identifier", type: "input",placeholder:"",tips:"标识符名称，例如 code 返回值需要为json,留空时当结果包含成功状态码里的内容时才认为是成功", default: "code"},';
        echo '{title: "成功状态码", name: "api_success", type: "input",placeholder:"",tips: "填写成功时返回的状态码，成功时才更改发货状态", default: "1"},';
        echo '{title: "失败重试次数", name: "api_num", type: "input",placeholder:"",tips: "通知失败时进行重新发送的次数，留空或0不重试", default: "3"},';
        echo '{title: "成功信息类型", name: "api_success_type", type: "select",dict:[{id:"0",name:"文本"},{id:"1",name:"JSON"}],placeholder:"请选择", default: "1"},';
        echo '{title: "成功返回信息", name: "api_success_text", type: "input",placeholder:"",tips: "通知成功时返回的信息，查询订单时可以看到,类型为文本时直接输出，为json时需填写标识", default: "msg"},';
        echo '{title: "失败信息类型", name: "api_failure_type", type: "select",dict:[{id:"0",name:"文本"},{id:"1",name:"JSON"}],placeholder:"请选择", default: "1"},';
        echo '{title: "失败返回信息", name: "api_failure_text", type: "input",placeholder:"",tips: "通知失败时返回的信息，查询订单时可以看到,类型为文本时直接输出，为json时需填写标识", default: "msg"},';
        echo '{title: "附加头", name: "api_headers", type: "json",tips: "在POST发送时，将会携带该信息发送至你填写的API", default: ""},';
        echo '{title: "附加参数", name: "api_extend", type: "json",tips: "在POST发送时，将会携带该信息发送至你填写的API", default: ""},';
    }
     #[Hook(point: \App\Consts\Hook::USER_VIEW_COMMODITY_POST)]
    public function Commodity2Post(): void
    {
        echo '{title: "请求方式", name: "api_request_type", type: "select",dict:[{id:"0",name:"不发送"},{id:"1",name:"POST"},{id:"2",name:"GET"}],placeholder:"请选择",tips: "选择商品请求的方式", default: "0"},';
        echo '{title: "API地址", name: "api_url", type: "input",placeholder:"带http://或https://的地址",tips: "该商品使用的api", default: ""},';
        echo '{title: "通信密钥", name: "api_key", type: "input",placeholder:"",tips: "用作与签名的通讯密钥", default: ""},';
        echo '{title: "签名方式", name: "api_sign_type", type: "select",dict:[{id:"0",name:"MD5"}],placeholder:"请选择", default: "0"},';
        echo '{title: "签名说明", name: "api_explain2", type: "explain",placeholder:"签名算法详见/app/Util/Str.php类中的generateSignature方法。参考文档:https://faka.bb95.cn/app/Plugin/Demo/Wiki/Index.html#/"},';
        echo '{title: "结果标识符", name: "api_identifier", type: "input",placeholder:"",tips:"标识符名称，例如 code 返回值需要为json,留空时当结果包含成功状态码里的内容时才认为是成功", default: "code"},';
        echo '{title: "成功状态码", name: "api_success", type: "input",placeholder:"",tips: "填写成功时返回的状态码，成功时才更改发货状态", default: "1"},';
        echo '{title: "失败重试次数", name: "api_num", type: "input",placeholder:"",tips: "通知失败时进行重新发送的次数，留空或0不重试", default: "3"},';
        echo '{title: "成功信息类型", name: "api_success_type", type: "select",dict:[{id:"0",name:"文本"},{id:"1",name:"JSON"}],placeholder:"请选择", default: "1"},';
        echo '{title: "成功返回信息", name: "api_success_text", type: "input",placeholder:"",tips: "通知成功时返回的信息，查询订单时可以看到,类型为文本时直接输出，为json时需填写标识", default: "msg"},';
        echo '{title: "失败信息类型", name: "api_failure_type", type: "select",dict:[{id:"0",name:"文本"},{id:"1",name:"JSON"}],placeholder:"请选择", default: "1"},';
        echo '{title: "失败返回信息", name: "api_failure_text", type: "input",placeholder:"",tips: "通知失败时返回的信息，查询订单时可以看到,类型为文本时直接输出，为json时需填写标识", default: "msg"},';
        echo '{title: "附加头", name: "api_headers", type: "json",tips: "在POST发送时，将会携带该信息发送至你填写的API", default: ""},';
        echo '{title: "附加参数", name: "api_extend", type: "json",tips: "在POST发送时，将会携带该信息发送至你填写的API", default: ""},';
    }
}