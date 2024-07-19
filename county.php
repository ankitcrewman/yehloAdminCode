<?php

$curl = curl_init();

$api_key = 'a2lidzI5OVRuclRkTGt3anB2OTdwZjRscVVuMFBFelZMckZ3OGdWdA==';

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.countrystatecity.in/v1/states',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array(
        'X-CSCAPI-KEY: ' . $api_key
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo 'cURL Error #: ' . $err;
} else {
    echo $response;
}
