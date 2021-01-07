<?php


namespace App\Console;



use NiuGengYun\EasyTBK\Factory;
use Illuminate\Console\Command;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkContentGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkCouponGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkItemCouponGetRequest;
use NiuGengYun\EasyTBK\TaoBao\Request\TbkItemInfoGetRequest;

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
        $req = new TbkItemCouponGetRequest();
        $req->set("610769309342");
        dd($client->execute($req));

    }
}