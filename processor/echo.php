<?php
/**
 * Echo Processor for testing purpose
 */

header('Content-Type: application/json');

$response->header("Content-Type", "application/json");
$response->end(json_encode(['status'=>'success','data'=>[
    'HEADER'=>$req->header,
    'GET'=>$req->get,
    'POST'=>$req->post,
    'FILES'=>$req->files,
    'RAW'=>$req->rawcontent
]]));

//remove every upload files
if(isset($req->files) && count($$eq->files)>0){
    foreach($req->files as $k => $v){
        if (file_exists($v['tmp_name'])){
            unlink($v['tmp_name']);
        }
    }
}