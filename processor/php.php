<?php
/**
 * PHP Processor will execute PHP Code you provide
 */

header('Content-Type: '.$route['content_type']);

ob_start();
eval("?>".$route['content']);
$result = ob_get_contents();
ob_end_clean();

$response->header("Content-Type", $route['content_type']);
$response->end($result);