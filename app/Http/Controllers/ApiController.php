<?php


namespace App\Http\Controllers;


use Illuminate\Support\Facades\Redis;

class ApiController extends Controller
{
    private $appId = 'wx478e8fc2f38ea123';
    private $appSecret = 'dd5e5f2a2b809fcc46976605f715d43e';

    public function test()
    {
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
                event(new WxOperateEvent($postObj));

                switch ($RX_TYPE) {
                    case "text":
                        $resultStr = $this->receiveText($postObj);
                        break;
                    case "image":
//                    $resultStr = $this->receiveImage($postObj);
                        $resultStr = '暂不支持图片消息';
                        break;
                    case "voice":
//                    $resultStr = $this->receiveVoice($postObj);
                        $resultStr = '暂不支持语音消息';
                        break;
                    case "event":
                        $resultStr = $this->receiveEvent($postObj);
                        break;
                    default:
                        $resultStr = "unknow msg type: " . $RX_TYPE;
                        break;
                }
                $time = time();
                if (is_array($resultStr)) {

                    // 获取到关键字，回复图文消息
                    $newsTplHead = "<xml>
                <ToUserName><![CDATA[%s]]></ToUserName>
                <FromUserName><![CDATA[%s]]></FromUserName>
                <CreateTime>%s</CreateTime>
                <MsgType><![CDATA[news]]></MsgType>
                <ArticleCount>%d</ArticleCount>
                <Articles>";
                    $newsTplBody = "<item>
                <Title><![CDATA[%s]]></Title>
                <Description><![CDATA[%s]]></Description>
                <PicUrl><![CDATA[%s]]></PicUrl>
                <Url><![CDATA[%s]]></Url>
                </item>";
                    $newsTplFoot = "</Articles>
                </xml>";
                    $newsTplHead = sprintf($newsTplHead, $postObj->FromUserName, $postObj->ToUserName, $time, count($resultStr));   // 限制死返回一条
                    $body = "";
                    foreach ($resultStr as $rekey => $revalue) {
                        if (isset($revalue['description'])) {
                            $revalue['description'] = str_replace("</p><p>", "\n", $revalue['description']);
                            $revalue['description'] = str_replace([ '<p>', '</p>' ], '', $revalue['description']);
                        } else {
                            $revalue['description'] = '';
                        }
                        $body .= sprintf($newsTplBody, $revalue['title'], $revalue['description'], $revalue['image_url'], $revalue['url']);
                    }
                    return $newsTplHead . $body . $newsTplFoot;
                } else {
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
}