<?php

// ---------------------------------------------------------------
// Script to translate dustsensor custom api format to emoncms.org
// ---------------------------------------------------------------
// Allows both option to use emoncms username and password or
// to set the password as the apikey
$emoncms = "http://127.0.0.1";

// ---------------------------------------------------------------
// 1. Authentication
// ---------------------------------------------------------------
if (!isset($_SERVER["PHP_AUTH_USER"])) { echo "missing username\n"; die; }
if (!isset($_SERVER["PHP_AUTH_PW"])) { echo "missing password\n"; die; }

$username = $_SERVER["PHP_AUTH_USER"];
$password = $_SERVER["PHP_AUTH_PW"];

// Special case, if username is 'apikey', the apikey is the password
if ($username=="apikey") {
    $apikey = $password;
} else {
// Otherwise call the emoncms.org auth api to find the apikey
    $auth = json_decode(request("POST","$emoncms/user/auth.json","username=$username&password=$password"));

    if (!$auth->success) {
        echo "invalid username or password\n"; die;
    }

    $apikey = $auth->apikey_write;
}

// ---------------------------------------------------------------
// 2. Decode and post data
// ---------------------------------------------------------------
// Fetch the data which is in the request body
$data = json_decode(file_get_contents('php://input'));
// Check that sensordatavalues is present, indicates valid data?
if (isset($data->sensordatavalues)) {

    if (isset($data->esp8266id)) $node = "esp".$data->esp8266id;

    $json = array();
    foreach ($data->sensordatavalues as $keyval) {
        $name = $keyval->value_type;
        $value = $keyval->value;
        $json[$name] = $value;
    }

    // Post to emoncms.org:
    $json = json_encode($json);
    $result = request("POST","$emoncms/input/post.json","node=$node&data=$json&apikey=$apikey");
    // error_log($result);

} else {
    echo "missing sensordatavalues\n"; die;
}

// ---------------------------------------------------------------
// ---------------------------------------------------------------

function request($method,$url,$body)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
    if ($body!=null) curl_setopt($curl, CURLOPT_POSTFIELDS,$body);

    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,5);
    curl_setopt($curl, CURLOPT_TIMEOUT,10);

    $curl_response = curl_exec($curl);
    curl_close($curl);
    return $curl_response;
}
