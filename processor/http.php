<?php

/**
 * HTTP Processor is the proxy to another API
 */

$url = $route['content'];
if($_jml>0){
    $path = implode("/",$_path);
    $url .= '/'.$path;
}

if(!empty($req->server['query_string'])){
    $url .= "?".$req->server['query_string'];
}

if(!$url) {
    $response->header("Content-Type", "application/json");
    $response->end(json_encode(['status'=>'failed','message'=>"You need to pass in a target URL."]));
}

//settimeout
ini_set('default_socket_timeout',$route['timeout']);

$response = "";
switch ($req->server['request_method']) {
case 'POST':
    $response = makePCurl('POST',getPostData($req), $url);
    break;
case 'PUT':
    $response = makePCurl('PUT',getPostData($req), $url);
    break;
case 'PATCH':
    $response = makePCurl('PATCH',getPostData($req), $url);
    break;
case 'DELETE':
    $response = makeDeleteRequest($url);
    break;
case 'GET':
    $response = makeGetRequest($url);
    break;
default:
    $response->header("Content-Type", "application/json");
    $response->end(json_encode(['status'=>'failed','message'=>"This gateway only supports POST, PUT, PATCH, DELETE AND GET REQUESTS."]));
    return;
}

//remove every upload files
if(isset($req->files) && count($req->files)>0){
    foreach($req->files as $k => $v){
        if (file_exists($v['tmp_name'])){
            unlink($v['tmp_name']);
        }
    }
}

$response->header("Content-Type", $route['content_type']);
$response->end($response);


function getPostData($req){
    $data = $req->rawcontent;
    if(!empty($data))
        return $data;
    $post = array();
    if(isset($req->post) && count($req->post)>0){
        $post = array_merge($post,$req->post);
    }
    if(isset($req->files) && count($req->files>0)){
        foreach($req->files as $k => $v){
            if (function_exists('curl_file_create')) { // php 5.5+
                $post[$k] = curl_file_create($v['tmp_name'],$v['type'],$v['name']);
            } else { //
                $post[$k] = "@".realpath($v['tmp_name']);
            }
        }
    }
    return $post;
}

function makeDeleteRequest($url) {
    $ch = initCurl($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function makeGetRequest($url) {
    $ch = initCurl($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function makePCurl($type, $data, $url) {
    $ch = initCurl($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $type);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}


function initCurl($url) {
    global $route,$req;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $route['timeout']);
    curl_setopt($ch, CURLOPT_TIMEOUT, $route['timeout']);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $req->header);
    curl_setopt($ch, CURLOPT_USERAGENT, $req->header['user-agent']);
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, "handleHeaderLine");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return $ch;
}

/**
 * Return some header to client
 */
function handleHeaderLine( $curl, $header_line ) {
    global $response;
    //if redirect, we tell them
    if(strpos('location',$header_line)!==false){
        $response->redirect($header_line,302);
    }
    return strlen($header_line);
}