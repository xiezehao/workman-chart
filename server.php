<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/08/19 0019
 * Time: 14:31
 */

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Worker;
require 'Autoloader.php';

$clients=[];//保存客户端消息

//创建一个worker监听9090端口
$ws_worker=new Worker("websocket://192.168.1.101:8080");

//启动4个进程对外提供服务
$ws_worker->count=4;

function syncUsers(){
    global $clients;
    $users="users:".json_encode(array_column($clients,"name","ipp"));

    foreach ($clients as $ip=>$client){
        $client["conn"]->send($users);
    }
}

//当收到客户端发来的数据后
$ws_worker->onMessage=function ($connection,$data){

    global $clients;

    if(preg_match("/^login:(\w{3,20})/i",$data,$result)){

        $ip=$connection->getRemoteIp();
        $port=$connection->getRemotePort();
        if(!array_key_exists($ip.":".$port,$clients)){
//            $clients[$ip]=$result[1];
            $clients[$ip.":".$port]=["ipp"=>$ip.":".$port,"name"=>$result[1],"conn"=>$connection];
            $connection->send('notice:success');
            $connection->send("msg:welcome".$result[1]);
//            一旦有用户登录级䒑保存的客户端信息发送过去
//            $connection->send("users:".json_encode($clients));
//            $users="users:".json_encode(array_column($clients,"name","ip"));
//            foreach ($clients as $ip=>$client){
//                $client["conn"]->send($users);
//            }

//            echo $ip.":".$result[1]."login".PHP_EOL;
            syncUsers();
        }

    }elseif (preg_match("/^msg:(.*?)/isU",$data,$msgset)){

        if(array_key_exists($connection->getRemoteIp(),$clients)){

            echo "get msg:".$msgset[1].PHP_EOL;
            if($msgset[1]=="nihao"){
                $connection->send("msg:nihao".$clients[$connection->getRemoteIp()]);
            }

        }
    }elseif (preg_match("/^chat:\<(.*?)\>:(.*?)/isU",$data,$msgset)){
        $ipp=$msgset[1];
        $msg=$msgset[2];
        echo $ipp."==>".$msg.PHP_EOL;
        if (array_key_exists($ipp,$clients)){
            $clients[$ipp]["conn"]->send("msg:".$msg);
            echo $ipp."==>".$msg.PHP_EOL;
        }
    }

    $connection->onClose=function ($connection){
        global $clients;

        unset($clients[$connection->getRemoteIp().":".$connection->getRemotePort()]);

        syncUsers();

        echo "connection closed\n";
    };

};

Worker::runAll();
