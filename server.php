<?php

include "config.php";
include "vendor/autoload.php";

use Medoo\Medoo;
use \Firebase\JWT\JWT;

//Initializing mysql
$_db = new Medoo([
    // required
    'database_type' => 'mysql',
    'database_name' => $db_name,
    'server' => $db_host,
    'username' => $db_user,
    'password' => $db_pass,
    'port' => $db_port
]);

$json = null;

//Initializing redis
if (class_exists('Redis')) {
    $redis = new Redis();
    $redis->connect($redis_host, $redis_port);
    $redis->auth($redis_pass);
}else{
    $use_redis = false;
}

$http = new swoole_http_server($server_ip, $server_port);

$http->on('start', function ($server) {
    echo "Swoole http server is started\n";
});

$http->on('request', function ($req, $response) {
    global $_db,$redis,$use_redis;

    $_path = explode("/",substr($req->server['request_uri'],1));

    $env = 'production';
    if($_path[0]=='dev'){
        unset($_path[0]);
        $_path = array_values($_path);
        $env = 'development';
    }else if($_path[0]=='sta'){
        unset($_path[0]);
        $_path = array_values($_path);
        $env = 'staging';
    }
    //Field to get from database
    $fields = ['id', 'methods', 'route_type', 'content_type', 'db_id', 'auth_id', 'content', 'retry', 'retry_delay', 'timeout'];


    if(isset($_path[0],$_path[1],$_path[2])){
        // get cache from redis
        if($use_redis){
            $key = sha1(implode("/",[$env,$req->server['request_method'],$_path[0],$_path[1],$_path[2]]));
            $json = $redis->get($key);
        }
        if(empty($json)){
            $routes = $_db->select('api_routes', $fields,
                    ['AND'=>['version'=>$_path[0], 'category'=>$_path[1], 'function'=>$_path[2],'environment'=>$env,'enabled'=>1]]
                );
            if(!isset($routes) || !is_array($routes) || count($routes)==0){
                goto twopath;
            }
        }else{
            $route = json_decode($json,true);
        }
        unset($_path[0],$_path[1],$_path[2]);
        $_path = array_values($_path);
    }else if(isset($_path[0],$_path[1])){
        twopath:
        // get cache
        if($use_redis){
            $key = sha1(implode("/",[$env,$req->server['request_method'],$_path[0],$_path[1]]));
            $json = $redis->get($key);
        }
        if(empty($json)){
            $routes = $_db->select('api_routes', $fields,
                    ['AND'=>['version'=>$_path[0], 'category'=>$_path[1],'environment'=>$env,'enabled'=>1]]
                );
        }else{
            $route = json_decode($json,true);
        }
        unset($_path[0],$_path[1]);
        $_path = array_values($_path);
    }

    // no cache
    if(empty($route)){
        if(!isset($routes) || !is_array($routes) || count($routes)==0){
            $response->header("Content-Type", "application/json");
            $response->end(json_encode(['status'=>'failed','message'=>'No Route matched']));
            return;
        }

        foreach($routes as $route){
            //CHECK METHOD
            if(strpos($route['methods'],$req->server['request_method'])===false){
                $response->header('HTTP/1.0 405 Method Not Allowed');
                $response->end(json_encode(['status'=>'failed','message'=>'Method Not Allowed']));
                return;
            }else{
                //found it
                //put cache
                $redis->set($key, json_encode($route));
                break;
            }
        }
    }

    //recount
    $_jml = count($_path);

    //analytics
    $date = date("Y-m-d");
    $hour = date("H")*1;
    $where = ['AND'=>['route_id'=>$route['id'],'date'=>$date]];
    $val = $_db->get('api_stats',"$hour",$where);
    if($val=="0"||$val>0){
        $_db->update('api_stats',[$hour=>$val+1],$where);
    }else{
        $_db->insert('api_stats',['route_id'=>$route['id'],'date'=>$date,"$hour"=>1]);
    }
    //END analytics

    if($route['auth_id']>0){
        $auth = $_db->get('api_auth',['jwt_secret', 'expired', 'header'],['id'=>$route['auth_id']]);
        $token = $req->header[$auth['header']];
        if(empty($token)){
            $response->header('HTTP/1.0 401 Unauthorized');
            $response->end(json_encode(['status'=>'failed','message'=>'Unauthorized']));
            return;
        }else{
            try{
                $decoded = JWT::decode($token, $auth['jwt_secret'], array('HS256'));
            }catch(Exception $e){
                $response->header('HTTP/1.0 401 Unauthorized');
                $response->end(json_encode(['status'=>'failed','message'=>'Unauthorized.']));
                return;
            }
        }
    }

    //find processor based route type
    if(file_exists('processor/'.$route['route_type'].'.php')){
        include 'processor/'.$route['route_type'].'.php';
    }else{
        $response->header("Content-Type", "application/json");
        $response->end(json_encode(['status'=>'failed','message'=>'No Route matched']));
        return;
    }
});


$http->start();