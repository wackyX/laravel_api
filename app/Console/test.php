<?php


namespace App\Console;


use App\lib\extendApi\TbkDgMaterialOptopnalRequestNew;
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
        111
        $client = Factory::taobao();
        $req = new TbkDgMaterialOptopnalRequestNew();
        $req->setQ('狗狗零食磨牙棒耐咬除口臭补钙幼犬泰迪大型犬金毛拉布拉多牛骨棒');
        $req->setAdzoneId('111152500099');
        $req->setSellerId('2208513976441');
        $data = $client->execute($req);
        dd($data);
    }
}