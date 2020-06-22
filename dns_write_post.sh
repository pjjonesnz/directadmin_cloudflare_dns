#!/usr/local/bin/php
<?php
/**
 * DirectAdmin DNS to Cloudflare sync
 * 
 * Script to synchronize DirectAdmin DNS settings with your Cloudflare hosted ns
 * 
 * @author Paul Jones (info@beyondthebox.co.nz)
 * @version 1.1.6
 * @license MIT
 * @link https://github.com/pjjonesnz/directadmin_cloudflare_dns
 * 
 */

/**
 * Add your email address and CloudFare API Key below
 */
$cloudflare_email = '';
$cloudflare_api_key = '';

/**
 * Logging options to sort out any problems
 */
$log_messages = false;
$log_to_file = true;
$log_filename = '/tmp/cloudflare_dns_messages.log';

if(in_array('verify', $argv)) {
    require('da_cloudflare_dns_sync/verify_connection.php');
    exit;
}

// Check to see if cloudfare is set as the name server for this domain
$ns1 = getenv('NS1');
if (strpos($ns1, 'ns.cloudflare.com') !== false) {
    require('da_cloudflare_dns_sync/dns_write_post_cloudflare.php');
}

?>