<?php


namespace App\Http\Controllers;


use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Justmd5\DaTaoKe\Api;
use Justmd5\DaTaoKe\DaTaoKe;
use Psy\Util\Str;

class ApiController extends Controller
{
    private $appId = 'wx478e8fc2f38ea123';
    private $appSecret = 'dd5e5f2a2b809fcc46976605f715d43e';

    public $host = '';//请求地址
    public $dataokeKey = '';//app_key
    public $dataokeSecret = '';//app_secret
    public $version = '';//版本号

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
                Log::info($RX_TYPE);
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
        Log::info($res);
        return $str;
    }

    private function receiveEvent($postObj)
    {
        $event = $postObj->Event;
        // 用户关注公众号 补全/注册用户信息,添加统计
        if ($event == 'subscribe') {
            $str = '欢迎关注';
            return $str;
        }
    }

    public function taokouling($str)
    {
        $url = 'https://openapi.dataoke.com/api/tb-service/parse-content';
        $data = [
            'content' => $str,
            'version' => 'v1.0.0',
            'appKey'  => env('TAOKOULING_API_KEY'),
        ];
        $this->host = $url;
        $this->dataokeKey = env('TAOKOULING_API_KEY');
        $this->dataokeSecret = env('TAOKOULING_API_SECRET');
        $this->version = 'v1.0.0';
        $params = [ 'content' => $str ];
        $data = $this->request($params);
//        $data['sign'] = $this->makeSignDataoke($data, env('TAOKOULING_API_SECRET'));
//        $url = $url . '?' . http_build_query($data);
//        $res = $this->requestUrl($url);
        return $data;
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

    function makeSign($data, $appSecret)
    {
        ksort($data);
        $str = '';
        foreach ($data as $k => $v) {
            $str .= '&' . $k . '=' . $v;
        }
        $str = trim($str, '&');
        $sign = strtoupper(md5($str . '&key=' . $appSecret));
        return $sign;
    }

    function request($params, $type = "GET")
    {
        $host = $this->host;
        $appKey = $this->dataokeKey;
        $appSecret = $this->dataokeSecret;
        $version = $this->version;
        if ($host == '' || $appKey == '' || $appSecret == '' || $version == '') {
            return json_encode(array( 'code' => -10001, 'msg' => "请完善参数" ));
        }
        $type = strtoupper($type);
        if (!in_array($type, array( "GET", "POST" ))) {
            return json_encode(array( 'code' => -10001, 'msg' => "只支持GET/POST请求" ));
        }
        //默认必传参数
        $data = [
            'appKey'  => $appKey,
            'version' => $version,
        ];
        //加密的参数
        $data = array_merge($params, $data);
        $data['sign'] = self::makeSign($data, $appSecret);
        try {
            if ($type == 'POST') {
                //拼接请求地址
                $url = $host;
                //执行请求获取数据
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                //https调用
                //curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
                //curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
                $header = [
                    'Content-Type: application/json'
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $output = curl_exec($ch);
                $a = curl_error($ch);
                if (!empty($a)) {
                    return json_encode(array( 'code' => -10003, 'msg' => $a ));
                }
                curl_close($ch);
                return $output;
            } else {
                //拼接请求地址
                $url = $host . '?' . http_build_query($data);
                //执行请求获取数据
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                $output = curl_exec($ch);
                $a = curl_error($ch);
                if (!empty($a)) {
                    return json_encode(array( 'code' => -10003, 'msg' => $a ));
                }
                curl_close($ch);
                return $output;
            }
        } catch (Exception $e) {
            var_dump($e->getMessage());
            return json_encode(array( 'code' => -10002, 'msg' => "请求超时或异常，请重试" ));
        }
    }
}