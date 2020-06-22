#!/usr/local/bin/php
<?php
/**
 * DirectAdmin DNS to Cloudflare sync
 * 
 * Script to synchronize DirectAdmin DNS settings with your Cloudflare hosted ns
 * 
 * @author Paul Jones (info@beyondthebox.co.nz)
 * @version 1.1.7
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
 * Use the TTL settings defined by DirectAdmin
 *
 * If set to false the default Cloudflare TTL setting will be used (which is currently set by Cloudflare to 300)
 */
$use_da_ttl = false;

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