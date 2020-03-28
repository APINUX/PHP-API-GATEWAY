<?php
/**
 * PLAIN Processor, will show content based your setup
 */

$response->header("Content-Type", $route['content_type']);
$response->end($route['content']);