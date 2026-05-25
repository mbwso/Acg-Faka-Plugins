<?php
declare(strict_types=1);

namespace App\Plugin\CardApi\Controller;

use App\Controller\Base\API\UserPlugin;
use App\Interceptor\Waf;
use App\Entity\CreateObjectEntity;
use App\Entity\DeleteBatchEntity;
use App\Entity\QueryTemplateEntity;
use App\Service\Query;
use App\Util\Date;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Exception\JSONException;
use App\Model\Card;

class Api extends UserPlugin
{

    #[Inject]
    private Query $query;
    /**
     * @return array
     * @throws JSONException
     */
    public function save(): array
    {
        $key = $_REQUEST['key'];

        if ($key != getPluginConfig("CardApi")['app_key']) {
            throw new JSONException("(`･ω･´)密钥不正确");
        }
        $commodityId = (int)$_REQUEST['commodity_id'];
        $race = (string)$_REQUEST['race'];

        if ($commodityId == 0) {
            throw new JSONException('(`･ω･´)请选择商品');
        }
        $cards = trim(trim((string)$_REQUEST['secret']), PHP_EOL);
        //进行批量插入
        if ($cards == '') {
            throw new JSONException('(`･ω･´)请至少添加1条卡密信息哦');
        }

        $cards = explode(PHP_EOL, $cards);
        $count = count($cards);

        $success = 0;
        $error = 0;
        $date = Date::current();

        $unique = (bool)$_REQUEST['unique'];

        foreach ($cards as $card) {
            $cardt = trim(trim($card), PHP_EOL);
            if ($cardt == "") {
                $error++; //error ++
                continue;
            }

            $pattern = "/#\[([\s\S]+?)\]#/";
            preg_match($pattern, $cardt, $cardy);
            $cardr = preg_replace($pattern, "", $cardt); //卡密

            if ($unique) {
                if (\App\Model\Card::query()->where("secret", "$cardr")->first()) {
                    $error++; //error ++
                    continue;
                }
            }

            $cardObj = new \App\Model\Card();
            $cardObj->commodity_id = $commodityId;
            $cardObj->owner = 0;
            if (isset($_REQUEST['note'])) {
                $cardObj->note = $_REQUEST['note'];
            }
            $cardObj->status = 0;

            if (isset($cardy[1])) {
                //预选信息
                $cardObj->draft = $cardy[1];
            }
            $cardObj->secret = $cardr;
            $cardObj->create_time = $date;

            if ($race){
                $cardObj->race = $race;
            }

            try {
                $cardObj->save();
                $success++;
            } catch (\Exception $e) {
                $error++; //error ++
            }
        }

        return $this->json(200, "共计导入:{$count}张卡密，成功:{$success}张，失败：{$error}张");
    }

     /**
     * @return array
     * @throws JSONException
     */
    public function num():array{
        $commodity_id=$_REQUEST['commodity_id'];
        if($commodity_id==""){
            throw new JSONException('(`･ω･´)请选择商品');
        }
        $json['code']=200;
        $json['commodity_id']=$commodity_id;
        $count = Card::query()->where("commodity_id", (int)$commodity_id)->where("status", 0);
        $race=$_GET['race'];
            if ($race) {
                $count = $count->where("race", $race);
                $json['race']=$race;
            }
            $json['num']=$count->count();
            $json['fil']=$_SERVER['SCRIPT_FILENAME'];
        return $json;
    }

}