<?php


namespace App\Http\Controllers;


use function GuzzleHttp\Psr7\str;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use NiuGengYun\EasyTBK\Factory;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkCouponGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkDgMaterialOptionalRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkTpwdConvertRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkTpwdCreateRequest;


class ApiController extends Controller
{
    private $appId = 'wx478e8fc2f38ea123';
    private $appSecret = 'dd5e5f2a2b809fcc46976605f715d43e';

    public function test()
    {
        Log::info('123');
        dd('success');
    }

    public function checkToken()
    {
        header("Content-type: text/html; charset=utf-8");
        //1.将timestamp,nonce,toke按字典顺序排序
        $timestamp = $_GET['timestamp'];
        $nonce = $_GET['nonce'];
        $token = 'dnDj3v24t6QLVFBfMoKbgQAxGYLpxL67';
        $signature = $_GET['signature'];
        $array = array( $timestamp, $nonce, $token );
        //2.将排序后的三个参数拼接之后用sha1加密
        $tmpstr = implode('', $array);
        $tmpstr = sha1($tmpstr);
        //3.将加密后的字符串与signature进行对比，判断该请求是否来自微信
        if ($tmpstr == $signature) {
            echo $_GET['echostr'];
            exit;
        }
    }

    public function getAccessToken()
    {
        $redisKey = 'access_token';
        if ($accessToken = Redis::get($redisKey)) {
            return $accessToken;
        } else {
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=' . $this->appId . '&secret=' . $this->appSecret;
            $data = json_decode($this->requestUrl($url), true);
            if (isset($data['access_token'])) {
                Redis::setex($redisKey, $data['expires_in'], $data['access_token']);
                return $data['access_token'];
            } else {
                return false;
            }

        }
    }


    public function responseMsg()
    {
        $postStr = file_get_contents('php://input');
        if (!empty($postStr)) {
            try {
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $RX_TYPE = trim($postObj->MsgType);

                if (($RX_TYPE == 'event') && (strtoupper((string)$postObj->Event) == 'TEMPLATESENDJOBFINISH')) {
                    return '';
                }
                // 唤起事件
                // event(new WxOperateEvent($postObj));
                switch ($RX_TYPE) {
                    case "text":
                        $resultStr = $this->receiveText($postObj);
                        break;
                    case "event":
                        $resultStr = $this->receiveEvent($postObj);
                        break;
                    default:
                        $resultStr = "unknow msg type: " . $RX_TYPE;
                        break;
                }
                $time = time();
                if ($resultStr) {
                    $textTpl = "<xml>
                        <ToUserName><![CDATA[%s]]></ToUserName>
                        <FromUserName><![CDATA[%s]]></FromUserName>
                        <CreateTime>%s</CreateTime>
                        <MsgType><![CDATA[text]]></MsgType>
                        <Content><![CDATA[%s]]></Content>
                        <FuncFlag>0</FuncFlag>
                        </xml>";

                    $resultStr = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, $time, $resultStr);
                    return $resultStr;
                }

            } catch (\Exception $e) {
                return '';
            }
        }
        return '';
    }

    private function receiveText($postObj)
    {
        $postObj->Content = trim($postObj->Content);
        $str = $postObj->Content;

        $res = $this->taokouling($str);
        if (!$res) {
            return '很遗憾，没有找到优惠券';
        }
        $goodId = $res['goodsId'];
        Log::info('good_id:' . $goodId);

        $res = $this->getShopName($goodId);
        if (!$res) {
            return '虽然没有找到优惠券，但是复制链接打开也是对我的一种支持噢
' . $this->zhuanlian($goodId);
        }
        $goodName = $res['title'];
        $shopName = $res['shopName'];
        $couponId = $this->quanSelect($goodName, $shopName);
        Log::info('goodName:' . $goodName);
        Log::info('$shopName:' . $shopName);
        Log::info('$couponId:' . $couponId);
        if (!$couponId) {
            return '虽然没有找到优惠券，但是复制链接打开也是对我的一种支持噢
' . $this->zhuanlian($goodId);
        }
        $couponId = $this->getActivity($goodId, $couponId);
        Log::info('goodId' . $goodId . 'couponId' . $couponId);
        if (!$res) {
            return '虽然没有找到优惠券，但是复制链接打开也是对我的一种支持噢
' . $this->zhuanlian($goodId);
        }

        $link = $this->zhuanlian($goodId, $couponId);
        if ($link) {
            return '恭喜找到了优惠券，复制这条信息至淘宝打开' . $link;
        } else {
            return '虽然没有找到优惠券，但是复制链接打开也是对我的一种支持噢
' . $this->zhuanlian($goodId);
        }


    }

    private function receiveEvent($postObj)
    {
        $event = $postObj->Event;
        // 用户关注公众号 补全/注册用户信息,添加统计
        if ($event == 'subscribe') {
            $str = '欢迎关注,跟我一起省钱
长按淘宝商品标题，选择复制链接
发送至公众号，
复制返回的淘口令至淘宝
领取优惠券。';
            return $str;
        }
    }

    public function taokouling($str)
    {
        $url = 'https://openapi.dataoke.com/api/tb-service/parse-taokouling';
        $time = time() * 1000;
        $data = [
            'version' => 'v1.0.0',
            'appKey'  => env('TAOKOULING_API_KEY'),
            'nonce'   => 123456,
            'timer'   => $time
        ];
        $sign = $this->makeSignDataoke(env('TAOKOULING_API_KEY'), env('TAOKOULING_API_SECRET'), 123456, $time);
        $data['signRan'] = $sign;
        $url = $url . '?' . http_build_query($data) . '&content=' . $str;
        $data = $this->requestUrl($url);
        Log::info('data:' . json_encode($data));
        Log::info('taokouling_url:' . $url);
        return $data['data'];
    }

    public function requestUrl($url, $flag = 0, $type = 0, $post_data = array(), $headers = array())
    {

        // 初始化一个 cURL 对象
        $curl = curl_init();
        // 设置你需要抓取的URL
        curl_setopt($curl, CURLOPT_URL, $url);
        // 设置header
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        // 设置cURL 参数，要求结果保存到字符串中还是输出到屏幕上。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        if ($type == 1) {       // post请求
            curl_setopt($curl, CURLOPT_POST, 1);
            $post_data = is_array($post_data) ? http_build_query($post_data) : $post_data;
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        }
        // 运行cURL，请求网页
        $data = curl_exec($curl);
        // 关闭URL请求
        curl_close($curl);

        if (!$flag) {
            $data = json_decode($data, true);
        }
        return $data;
    }

    public function dataokeGoodsDetail($id)
    {
        $url = 'https://openapi.dataoke.com/api/goods/get-goods-details';
        $time = time() * 1000;
        $data = [
            'version' => 'v1.2.3',
            'appKey'  => env('TAOKOULING_API_KEY'),
            'nonce'   => 123456,
            'timer'   => $time
        ];
        $sign = $this->makeSignDataoke(env('TAOKOULING_API_KEY'), env('TAOKOULING_API_SECRET'), 123456, $time);
        $data['signRan'] = $sign;
        $url = $url . '?' . http_build_query($data) . '&goodsId=' . $id;
        $data = $this->requestUrl($url);
        return $data['data'];
    }

    private function makeSignDataoke($appKey, $appSecret, $nonce, $timer)
    {

        $str = 'appKey=' . $appKey . '&timer=' . $timer . '&nonce=' . $nonce . '&key=' . $appSecret;
        $sign = strtoupper(md5($str));
        return $sign;
    }


    private function quanSelect($str, $shopName)
    {
        $client = Factory::taobao();
        $req = new TbkDgMaterialOptionalRequest();
        $req->setQ($str);
        $req->setAdzoneId('111152500099');
        $req->setPageSize('100');
        $req->setHasCoupon('true');
        $data = $client->execute($req);
        $data = json_decode(json_encode($data), true);
        Log::info('quanselect' . json_encode($data));
        if (!isset($data['result_list'])) {
            return false;
        }
        foreach ($data['result_list']['map_data'] as $v) {
            if ($v['shop_title'] == $shopName) {
                return $v['coupon_id'];
            }
        }
        return false;
    }

    private function getShopName($goodId)
    {
        $url = 'https://openapi.dataoke.com/api/goods/get-goods-details';
        $time = time() * 1000;
        $data = [
            'version' => 'v1.2.3',
            'appKey'  => env('TAOKOULING_API_KEY'),
            'nonce'   => 123456,
            'timer'   => $time
        ];
        $sign = $this->makeSignDataoke(env('TAOKOULING_API_KEY'), env('TAOKOULING_API_SECRET'), 123456, $time);
        $data['signRan'] = $sign;
//        $dataoke = new \CheckSign();
//        Log::info('aaaaa');
//        $dataoke->host = $url;
//        $dataoke->appKey = env('TAOKOULING_API_KEY');
//        $dataoke->appSecret = env('TAOKOULING_API_SECRET');
//        $dataoke->version = 'v1.0.0';
//        $params = array();
//        $params['content'] = $str;
//        $data = $dataoke->request($params);

        $url = $url . '?' . http_build_query($data) . '&goodsId=' . $goodId;
        Log::info('url:' . $url);
        $data = $this->requestUrl($url);
        Log::info('getshopname' . json_encode($data));
        if (isset($data['data'])) {
            return $data['data'];
        } else {
            return false;
        }

    }

    private function getActivity($goodId, $couponId)
    {
        $client = Factory::taobao();
        $req = new TbkCouponGetRequest();
        $req->setItemId($goodId);
        $req->setActivityId($couponId);
        $data = $client->execute($req);
        Log::info('log' . json_encode($data));
        return $data->data->coupon_activity_id;

    }

    private function zhuanlian($goodId, $couponId = '')
    {
        $url = 'https://openapi.dataoke.com/api/tb-service/get-privilege-link';
        $time = time() * 1000;
        $data = [
            'version' => 'v1.3.1',
            'appKey'  => env('TAOKOULING_API_KEY'),
            'nonce'   => 123456,
            'timer'   => $time
        ];
        $sign = $this->makeSignDataoke(env('TAOKOULING_API_KEY'), env('TAOKOULING_API_SECRET'), 123456, $time);
        $data['signRan'] = $sign;
//        $dataoke = new \CheckSign();
//        Log::info('aaaaa');
//        $dataoke->host = $url;
//        $dataoke->appKey = env('TAOKOULING_API_KEY');
//        $dataoke->appSecret = env('TAOKOULING_API_SECRET');
//        $dataoke->version = 'v1.0.0';
//        $params = array();
//        $params['content'] = $str;
//        $data = $dataoke->request($params);

        $url = $url . '?' . http_build_query($data) . '&goodsId=' . $goodId;
        if ($couponId) {
            $url .= '&couponId=' . $couponId;
        }
        $data = $this->requestUrl($url);

        return $data['data']['tpwd'];
    }


}