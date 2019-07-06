<?php
require __DIR__.'/vendor/autoload.php';
use \Ovh\Api;

$configNamesAndHeaders = array(
    'endpoint'          => 'X-OVH-ENDPOINT',
    'applicationKey'    => 'X-OVH-AK',
    'applicationSecret' => 'X-OVH-AS',
    'consumerKey'       => 'X-OVH-CK',
    'redirect'          => null,
    'rights'            => null,
);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-OVH-BATCH, Authorization, X-OVH-USER, X-OVH-NIC, '.implode(', ', array_values($configNamesAndHeaders)));
if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    header('Content-Type: application/json');
    echo json_encode(array('message' => 'OPTIONS request is always allowed'));
    exit(0);
}

require_once(__DIR__.'/config.php');

if(!$config) {
    return returnJson(500, ['application/json'], ['message' => 'Missing or invalid configuration']);
}

$defaultConfig = array(
    'endpoint' => 'ovh-eu',
    'redirect' => 'http'.($_SERVER['HTTPS']=='on'?'s':'').'://'.$_SERVER['HTTP_HOST'].'/',
    'applicationKey'    => null,
    'applicationSecret' => null,
    'consumerKey'       => null,
    'rights' => array(
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
    ),
);

$currentConfig = $config;

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
if(isset($_SERVER['HTTP_X_OVH_DEBUG'])) {
    header('X-OVH-DEBUG-REQUEST-URI: '.$_SERVER['REQUEST_URI']);
    header('X-OVH-DEBUG-API-URL: '.$url);
}

foreach($configNamesAndHeaders as $configName => $header) {
    $currentConfig[$configName] = $defaultConfig[$configName];
    $phpHeader = 'HTTP_'.str_replace('-', '_', $header);
    if($header and isset($_COOKIE[$configName])) {
        $currentConfig[$configName] = $_COOKIE[$configName];
    } elseif($header and isset($_SERVER[$phpHeader])) {
        $currentConfig[$configName] = $_SERVER[$phpHeader];
    } elseif(isset($config[$configName])) {
        $currentConfig[$configName] = $config[$configName];
    }
    
    if(!$currentConfig[$configName]) {
        if($configName != 'consumerKey' or ($url != '/login' and $url != '/logout')) {
            return returnJson(500, ['application/json'], ['message' => 'Invalid '.$configName]);
        }
    }
}

if($url == '/login') {
    $conn = new Api($currentConfig['applicationKey'], $currentConfig['applicationSecret'], $currentConfig['endpoint']);
    $credentials = $conn->requestCredentials($currentConfig['rights'], $currentConfig['redirect']);
    setcookie('consumerKey', $credentials['consumerKey'], time()+3600*24*365, '/');
    $currentConfig['consumerKey'] = $credentials['consumerKey'];
    foreach($configNamesAndHeaders as $configName => $header) {
        if($header) {
            if(isset($config[$configName]) && $config[$configName] == $currentConfig[$configName]){
                setcookie($configName, '', time()-1000, '/');
            } else {
                setcookie($configName, $currentConfig[$configName], time()+3600*24*365, '/');
            }
        }
    }
    header('Location: '. $credentials['validationUrl']);
    exit(0);
} elseif($url == '/logout') {
    foreach($configNamesAndHeaders as $configName => $header) {
        setcookie($configName, '', time()-1000, '/');
    }
    header('Location: '.$currentConfig['redirect']);
    exit(0);
}

if(!$currentConfig['consumerKey']) {
    return returnJson(500, ['application/json'], ['message' => 'User not logged-in']);
}

$conn = new Api($currentConfig['applicationKey'], $currentConfig['applicationSecret'], $currentConfig['endpoint'], $currentConfig['consumerKey']);
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
