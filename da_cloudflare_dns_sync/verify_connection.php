<?php

/**
 * Load Cloudflare PHP API
 */
require_once('vendor/autoload.php');
$key = new \Cloudflare\API\Auth\APIKey($cloudflare_email, $cloudflare_api_key);
$adapter = new Cloudflare\API\Adapter\Guzzle($key);
$user = new \Cloudflare\API\Endpoints\User($adapter);

echo 'Your user ID is: ' . $user->getUserID() . PHP_EOL;

?>