<?php


namespace App\Console;


use App\lib\extendApi\TbkDgMaterialOptopnalRequestNew;
use Illuminate\Support\Facades\Log;
use NiuGengYun\EasyTBK\Factory;
use Illuminate\Console\Command;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkContentGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkCouponGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkDgMaterialOptionalRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkItemCouponGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkItemInfoGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkTpwdCreateRequest;

class test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test';


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
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
        $url = $url . '?' . http_build_query($data) . '&goodsId=622076101827&couponId=a2c279d5ab0741b9897c3752c2db60da';
        $data = $this->requestUrl($url);

        dd($data['data']['tpwd']);
    }

    private function makeSignDataoke($appKey, $appSecret, $nonce, $timer)
    {

        $str = 'appKey=' . $appKey . '&timer=' . $timer . '&nonce=' . $nonce . '&key=' . $appSecret;
        $sign = strtoupper(md5($str));
        return $sign;
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


}