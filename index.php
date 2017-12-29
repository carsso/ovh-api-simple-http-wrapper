<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;
session_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-OVH-BATCH, Authorization, X-OVH-USER, X-OVH-NIC, AK, AS, CK, X-OVH-AK, X-OVH-AS, X-OVH-CK');
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS')
{
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'OPTIONS request is always allowed'));
    exit(0);
}

$consumerKey = null;
$applicationKey = null;
$applicationSecret = null;

$configFile = __DIR__.'/config.json';

$configString = file_get_contents($configFile);
if(!$configString) {
    return returnJson(500, ['application/json'], ['message' => 'Missing configuration']);
}
$config = json_decode($configString, true);
if(!$config) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid configuration']);
}

if(!isset($config['endpoint'])) {
    return returnJson(500, ['application/json'], ['message' => 'Missing API endpoint in configuration']);
}
$endpoint = $config['endpoint'];
if(empty($endpoint)) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid API endpoint in configuration']);
}

if(!isset($config['appKey'])) {
    return returnJson(500, ['application/json'], ['message' => 'Missing application key in configuration']);
}
$applicationKey = $config['appKey'];
if(empty($applicationKey)) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid application key in configuration']);
}

if(!isset($config['appSecret'])) {
    return returnJson(500, ['application/json'], ['message' => 'Missing application secret in configuration']);
}
$applicationSecret = $config['appSecret'];
if(empty($applicationSecret)) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid application secret in configuration']);
}

if(!isset($config['consumerKey'])) {
    return returnJson(500, ['application/json'], ['message' => 'Missing consumer key in configuration']);
}
$consumerKey = $config['consumerKey'];
if(empty($consumerKey)) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid consumer key in configuration']);
}

if(isset($_SERVER['HTTP_X_OVH_CK']))
{
    $consumerKey = $_SERVER['HTTP_X_OVH_CK'];
}
if(isset($_SERVER['HTTP_X_OVH_AK']))
{
    $applicationKey = $_SERVER['HTTP_X_OVH_AK'];
}
if(isset($_SERVER['HTTP_X_OVH_AS']))
{
    $applicationSecret = $_SERVER['HTTP_X_OVH_AS'];
}

$url = '';
$batch = null;
if(isset($_SERVER['HTTP_X_OVH_BATCH'])) {
    $batch = $_SERVER['HTTP_X_OVH_BATCH'];
}
if(isset($_GET['batch'])) {
    $batch = $_GET['batch'];
}
$method = $_SERVER['REQUEST_METHOD'];
$url = $_SERVER['REQUEST_URI'];
$prefix = dirname($_SERVER['PHP_SELF']);
if($prefix != '/') {
    $url = preg_replace('/^'.preg_quote($prefix, '/').'/', '', $url);
}
if(isset($_SERVER['HTTP_X_OVH_DEBUG']))
{
    header('X-OVH-DEBUG-REQUEST-URI: '.$_SERVER['REQUEST_URI']);
    header('X-OVH-DEBUG-API-URL: '.$url);
}

if(!$consumerKey)
{
    return returnJson(500, ['application/json'], ['message' => 'User not logged-in']);
}

$conn = new Api($applicationKey, $applicationSecret, $endpoint, $consumerKey);
$content = null;
$rawJson = file_get_contents("php://input");
if($rawJson) {
    $content = json_decode_ovh($rawJson, true);
}

$method = strtolower($method);
try {
    global $conn;
    $result = null;
    $headers = null;
    if($batch) {
        $headers = ['X-OVH-BATCH' => $batch];
    }
    $result = $conn->$method($url, $content, $headers);
    $statusCode = 200;
    $contentType = ['application/json'];
    $body = json_encode($result);
} catch (Exception $exception) {
    $response = $exception->getResponse();
    if ($response != null) {
        $statusCode = $response->getStatusCode();
        $contentType = $response->getHeader('Content-Type');
        $body = $response->getBody()->__toString();
    } else {
        $statusCode = 500;
        $contentType = ['application/json'];
        $body = ['message' => $exception->getMessage()];
    }
}
if($contentType[0] == 'application/json') {
    $body = json_decode($body);
}
return returnJson($statusCode, $contentType, $body);

function returnJson($statusCode, $contentType, $body) {
    http_response_code($statusCode);
    header('Content-Type: '.implode(', ', $contentType));
    if($contentType[0] == 'application/json') {
        $body = json_encode($body);
    }
    echo $body;
    exit(0);
}

function json_decode_ovh($json, $assoc = false) {
    $json = preg_replace('/(\w+):/i', '"\1":', $json);
    return json_decode($json, $assoc);
}
