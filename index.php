<?php
require __DIR__ . '/vendor/autoload.php';
use \Ovh\Api;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-OVH-BATCH, Authorization, X-OVH-USER, X-OVH-NIC, X-OVH-ENDPOINT, X-OVH-AK, X-OVH-AS, X-OVH-CK');
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS')
{
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'OPTIONS request is always allowed'));
    exit(0);
}

$configFile = __DIR__.'/config.json';

$configString = file_get_contents($configFile);
if(!$configString) {
    return returnJson(500, ['application/json'], ['message' => 'Missing configuration']);
}
$config = json_decode($configString, true);
if(!$config) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid configuration']);
}

$redirect = 'http'.($_SERVER['HTTPS']=='on'?'s':'').'://'.$_SERVER['HTTP_HOST'].'/';
$rights = array(
    [
        'method'    => 'GET',
        'path'      => '/*'
    ],
    [
        'method'    => 'POST',
        'path'      => '/*'
    ],
    [
        'method'    => 'PUT',
        'path'      => '/*'
    ],
    [
        'method'    => 'DELETE',
        'path'      => '/*'
    ],
);
$endpoint = 'ovh-eu';
$applicationKey = null;
$applicationSecret = null;
$consumerKey = null;

if(isset($config['redirect']))
{
    $redirect = $config['redirect'];
}

if(isset($config['rights']))
{
    $rights = $config['rights'];
}

if(isset($config['endpoint']))
{
    $endpoint = $config['endpoint'];
}
elseif(isset($_COOKIE['endpoint']))
{
    $endpoint = $_COOKIE['endpoint'];
}
elseif(isset($_SERVER['HTTP_X_OVH_ENDPOINT']))
{
    $endpoint = $_SERVER['HTTP_X_OVH_ENDPOINT'];
}

if(isset($config['appKey']))
{
    $applicationKey = $config['appKey'];
}
elseif(isset($_COOKIE['applicationKey']))
{
    $applicationKey = $_COOKIE['applicationKey'];
}
elseif(isset($_SERVER['HTTP_X_OVH_AK']))
{
    $applicationKey = $_SERVER['HTTP_X_OVH_AK'];
}

if(isset($config['appSecret']))
{
    $applicationSecret = $config['appSecret'];
}
elseif(isset($_COOKIE['applicationSecret']))
{
    $applicationSecret = $_COOKIE['applicationSecret'];
}
elseif(isset($_SERVER['HTTP_X_OVH_AS']))
{
    $applicationSecret = $_SERVER['HTTP_X_OVH_AS'];
}

if(isset($config['consumerKey']))
{
    $consumerKey = $config['consumerKey'];
}
elseif(isset($_COOKIE['consumerKey']))
{
    $consumerKey = $_COOKIE['consumerKey'];
}
elseif(isset($_SERVER['HTTP_X_OVH_CK']))
{
    $consumerKey = $_SERVER['HTTP_X_OVH_CK'];
}

if(!$endpoint) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid endpoint']);
}

if(!$applicationKey) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid application key']);
}

if(!$applicationSecret) {
    return returnJson(500, ['application/json'], ['message' => 'Invalid application secret']);
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

if($url == '/login')
{
    $conn = new Api($applicationKey, $applicationSecret, $endpoint);
    $credentials = $conn->requestCredentials($rights, $redirect);
    setcookie('consumerKey', $credentials['consumerKey'], time()+3600*24*365, '/');
    if(isset($config['appKey']) && $config['appKey'] == $applicationKey){
        setcookie('applicationKey', '', time()-1000, '/');
    } else {
        setcookie('applicationKey', $applicationKey, time()+3600*24*365, '/');
    }
    if(isset($config['appSecret']) && $config['appSecret'] == $applicationSecret){
        setcookie('applicationSecret', '', time()-1000, '/');
    } else {
        setcookie('applicationSecret', $applicationSecret, time()+3600*24*365, '/');
    }
    header('Location: '. $credentials['validationUrl']);
    exit(0);
}
elseif($url == '/logout')
{
    setcookie('consumerKey', '', time()-1000, '/');
    setcookie('applicationKey', '', time()-1000, '/');
    setcookie('applicationSecret', '', time()-1000, '/');
    header('Location: '. $redirect);
    exit(0);
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
