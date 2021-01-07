<?php


namespace App\Console;



use NiuGengYun\EasyTBK\Factory;
use Illuminate\Console\Command;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkContentGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkCouponGetRequest;
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
        $client = Factory::taobao();
        $req = new TbkTpwdCreateRequest();
        $req->setText("复制内容淘宝打开");
        $req->setUrl("https://uland.taobao.com/quan/detail?sellerId=1669558588&activityId=8c2bf9951b7a448182eaf89ae4fe2115");
        $data = $client->execute($req);
dd($data->data);
    }
}