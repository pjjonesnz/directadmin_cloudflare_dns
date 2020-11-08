<?php

/**
 * DirectAdmin DNS to Cloudflare sync
 */

require_once('vendor/autoload.php');
$key = new \Cloudflare\API\Auth\APIKey($cloudflare_email, $cloudflare_api_key);
$adapter = new Cloudflare\API\Adapter\Guzzle($key);
$zones = new \Cloudflare\API\Endpoints\Zones($adapter);

logMessage("------------------ dns_write_post.sh ------------------");

$domain = getenv('DOMAIN');

$errorList = array();
$hasErrors = false;

// Retrieve DNS Records from Environment Variables
$a = getenv('A');
$cname = getenv('CNAME');
$mx = getenv('MX');
$mx_full = getenv('MX_FULL');
$ns = getenv('NS');
$ptr = getenv('PTR');
$txt = getenv('TXT');
$spf = getenv('SPF');
$aaaa = getenv('AAAA');
$srv = getenv('SRV');

if (!isset($use_da_ttl)) {
    $use_da_ttl = false;
}

$aTTL = $use_da_ttl ? getenv('A_TIME') : 0;
$txtTTL = $use_da_ttl ? getenv('TXT_TIME') : 0;
$mxTTL = $use_da_ttl ? getenv('MX_TIME') : 0;
$cnameTTL = $use_da_ttl ? getenv('CNAME_TIME') : 0;
$ptrTTL = $use_da_ttl ? getenv('PTR_TIME') : 0;
$nsTTL = $use_da_ttl ? getenv('NS_TIME') : 0;
$aaaaTTL = $use_da_ttl ? getenv('AAAA_TIME') : 0;
$srvTTL = $use_da_ttl ? getenv('SRV_TIME') : 0;

// $serial = getenv('SERIAL');
// $srv_email = getenv('EMAIL');
// $domainIP = getenv('DOMAIN_IP');
// $serverIP = getenv('SERVER_IP');
// $ds = getenv('DS');
//$dsTTL = getenv('DS_TIME');
//$spfTTL = getenv('SPF_TIME');

// Retrieve zoneID for domain from Cloudflare - if zone doesn't exist add it

$zoneID = null;
try {
    $zoneID = $zones->getZoneID($domain);
} catch (\Exception $e) {
    if ($e->getMessage() == 'Could not find zones with specified name.') {
        // Create zone
        try {
            $result = $zones->addZone($domain);
            if ($result->name == $domain) {
                $zoneID = $result->id;
            } else {
                throw new Exception("Error Adding Domain", 1);
            }
        } catch (\Exception $e) {
            logMessage('Exception Caught: ' . $e->getMessage());
            exit;
        }
    } else {
        exit;
    }
}

logMessage('Zone ID for ' . $domain . ' - ' . $zoneID);

/**
 * Load settings json for domain, or defaults if custom domain settings not available
 */
$domain_settings = array('proxy_default' => false);
$settings_filename = __DIR__ . '/domains/' . $domain . '.json';

if (!file_exists($settings_filename)) {
    $settings_filename = __DIR__ . '/domains/default.json';
    if (!file_exists($settings_filename)) {
        logMessage('Error: default domain config file default.json missing from plugin.', true);
        $settings_filename = '';
    }
}
if ($settings_filename !== '') {
    $json_file = file_get_contents($settings_filename);
    if ($json_file !== false) {
        $load_settings = json_decode($json_file, true);
        if ($load_settings !== NULL) {
            $domain_settings = $load_settings;
        } else {
            logMessage('Error: Json error in default.json plugin config file.', true);
        }
    }
}

/**
 * Load existing DNS records for the domain
 */
$dns = new \Cloudflare\API\Endpoints\DNS($adapter);
$page = 0;
$per_page = 500;
$existingRecords = array();

do {
    $page++;
    $listRecords = $dns->listRecords($zoneID, '', '', '', $page, $per_page);
    $existingRecords = array_merge($existingRecords, $listRecords->result);
} while ($listRecords->result_info->total_pages > $page);

/**
 * Array of dns records to add to Cloudflare
 */
$recordsToAdd = array();

/**
 * Array of dns records that already exist on Cloudflare
 */
$recordsThatExist = array();

// NOTE don't parse NS records

// parse A records
$output = parseInput($a);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type'      => 'A',
            'name'      => qualifyRecordName($value->key),
            'content'   => $value->value,
            'ttl'       => $aTTL,
            'proxied'   => is_proxied('A', $value->key),
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse TXT records
$output = parseInput($txt);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type'      => 'TXT',
            'name'      => qualifyRecordName($value->key),
            'content'   => trim(preg_replace(array('/"\s+"/', '/"/'), array('', ''), trim($value->value, '()'))),
            'ttl'       => $txtTTL,
            'proxied'   => false
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse MX records
$output = parseInput($mx_full);
if (count($output) > 0) {
    foreach ($output as $value) {
        preg_match('/(\d+) (.*)/', $value->value, $parsedValue);
        $record = (object) array(
            'type'      => 'MX',
            'name'      => qualifyRecordName($value->key),
            'priority'  => $parsedValue[1],
            'content'   => qualifyRecordName($parsedValue[2]),
            'ttl'       => $mxTTL,
            'proxied'   => false,
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse CNAME records
$output = parseInput($cname);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type'      => 'CNAME',
            'name'      => qualifyRecordName($value->key),
            'content'   => qualifyRecordName($value->value),
            'ttl'       => $cnameTTL,
            'proxied'   => is_proxied('CNAME', $value->key),
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse PTR records
$output = parseInput($ptr);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type'      => 'PTR',
            'name'      => $value->key,
            'content'   => qualifyRecordName($value->value),
            'ttl'       => $ptrTTL,
            'proxied'   => false,
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse AAAA records
$output = parseInput($aaaa);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type'      => 'AAAA',
            'name'      => qualifyRecordName($value->key),
            'content'   => $value->value,
            'ttl'       => $aaaaTTL,
            'proxied'   => is_proxied('AAAA', $value->key),
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse SRV records
$output = parseInput($srv);
if (count($output) > 0) {

    foreach ($output as $value) {
        preg_match('/(\d+) (\d+) (\d+) (.*)/', $value->value, $parsedValue);

        $fullSRVname = qualifyRecordName($value->key);
        preg_match('/^(.*)\._(tcp|udp|tls)\.(.*)$/', $fullSRVname, $srv_match);

        $record = (object) array(
            'type'      => 'SRV',
            'name'      => $fullSRVname,
            'priority'  => $parsedValue[1],
            'content'   => qualifyRecordName($parsedValue[4]),
            'data'      => array(
                'name'      => $srv_match[3],
                'weight'    => (int) $parsedValue[2],
                'port'      => (int) $parsedValue[3],
                'target'    => qualifyRecordName($parsedValue[4]),
                'proto'     => '_' . $srv_match[2],
                'service'   => $srv_match[1],
                'priority'  => (int) $parsedValue[1],
            ),
            'ttl'       => $srvTTL,
            'proxied'   => false,
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

/**
 * Delete records from Cloudflare that don't exist in DirectAdmin
 * 
 * While ignoring nameserver records, go through all existingRecords and see if there is a match in recordsToAdd or recordsThatExist - if not, delete the record.
 */
$keysToDelete = array();
foreach ($existingRecords as $record) {
    if ($record->type != 'NS') {
        if (!isRecordCurrent($recordsToAdd, $recordsThatExist, $record)) {
            array_push($keysToDelete, $record->id);
            logMessage('Queue Record to Delete: ' . $record->id . ' - ' . $record->name . "\t" . $record->type . "\t" . $record->content);
        }
    }
}
if (count($keysToDelete) > 0) {
    foreach ($keysToDelete as $key) {
        $success = $dns->deleteRecord($zoneID, $key);
        logMessage('Delete Record ID ' . $key  . "\t" . ($success == true ? 'SUCCESSFUL' : 'FAILED'));
    }
}

/**
 * Add new records to Cloudflare
 */
foreach ($recordsToAdd as $record) {
    $priority = isset($record->priority) ? $record->priority : '';
    $data = isset($record->data) && count($record->data) > 0 ? $record->data : [];
    $proxied = $record->proxied;
    $ttl = $record->ttl > 0 ? $record->ttl : 0; // use default TTL
    try {
        $success = $dns->addRecord($zoneID, $record->type, $record->name, $record->content, $ttl, $proxied, $priority, $data);
        logMessage('Add Record: ' . $record->name . "\t" . $record->type . "\t" . $record->content . "\t" . ($success == true ? 'SUCCESSFUL' : 'FAILED') . "\t" . "Proxy: " . ($proxied ? 'on' : 'off'), !$success);
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        logMessage('Add Record: ' . $record->name . "\t" . $record->type . "\t" . $record->content . "\t" . 'FAILED', true);
        logMessage($e->getResponse()->getBody()->getContents(), true);
    }
}

logMessage('Result: ' . get_records_modified_text($keysToDelete) . ' deleted, ' . get_records_modified_text($recordsToAdd) . ' added.');

// Output any errors to be displayed in the DirectAdmin console
if ($hasErrors) {
    echo join("\n\n", $errorList);
    exit(1);
} else {
    exit(0);
}

function is_proxied($record_type, $record_name)
{
    global $domain_settings;
    if($record_name == 'localhost') {
        return false;
    }
    if (isset($domain_settings['proxy_record']) && isset($domain_settings['proxy_record'][$record_type])) {
        if(is_array($domain_settings['proxy_record'][$record_type])) {
            if(isset($domain_settings['proxy_record'][$record_type][$record_name])) {
                return $domain_settings['proxy_record'][$record_type][$record_name] === true;
            }
        }
        elseif(is_bool($domain_settings['proxy_record'][$record_type]) ) {
            return $domain_settings['proxy_record'][$record_type] === true;
        }
    } 
    return isset($domain_settings['proxy_default']) && $domain_settings['proxy_default'] === true;
}

function get_records_modified_text($mod_array)
{
    if (count($mod_array) == 1) {
        return '1 record';
    }
    return count($mod_array) . ' records';
}

/**
 * Log a message to the php error log
 */
function logMessage($message, $error = false)
{
    global $log_messages, $log_to_file, $log_filename, $errorList, $hasErrors;
    if ($error) {
        $hasErrors = true;
        $errorList[] = $message;
    }
    if (!$log_messages) {
        return;
    }
    $timestamp = date('Y-m-d H:i:s');
    if ($log_to_file) {
        error_log($timestamp . " - " . $message . "\n", 3, $log_filename);
    } else {
        error_log($timestamp . " - " . $message . "\n");
    }
}

/**
 * Search through array of records for a particular record
 */
function doesRecordExist($records, $record)
{
    global $use_da_ttl;
    if (count($records) == 0) {
        return false;
    }
    foreach ($records as $compare) {
        if ($record->type == 'SRV') {
            if (!isset($compare->data)) {
                continue;
            }
            $compare_data = (object) $compare->data;
            $record_data = (object) $record->data;
            if (
                compare_records($compare->type, $record->type) &&
                compare_records($compare->name, $record->name) &&
                compare_records($compare->proxied, $record->proxied) &&
                (!$use_da_ttl || compare_records($compare->ttl, $record->ttl)) &&
                compare_records($compare_data->weight, $record_data->weight) &&
                compare_records($compare_data->target, $record_data->target) &&
                compare_records($compare_data->proto, $record_data->proto) &&
                compare_records($compare_data->service, $record_data->service) &&
                compare_records($compare_data->priority, $record_data->priority) &&
                compare_records($compare_data->port, $record_data->port)
            ) {
                return true;
            }
        } else {
            if (
                compare_records($compare->type, $record->type) &&
                compare_records($compare->name, $record->name) &&
                compare_records($compare->content, $record->content) &&
                compare_records($compare->proxied, $record->proxied) &&
                (!$use_da_ttl || compare_records($compare->ttl, $record->ttl))
            ) {
                if ($record->type == 'MX' && !compare_records($compare->priority, $record->priority)) {
                    continue;
                }
                return true;
            }
        }
    }
    return false;
}

/**
 * Compare two records to see if they match
 * @param string $r1 Existing Record on Cloudflare
 * @param string $r2 Record on server
 */
function compare_records($r1, $r2)
{
    return $r1 == $r2;
}

/**
 * Search through recordsToAdd and recordsThatExist to see if the existing record should be kept or deleted
 */
function isRecordCurrent($recordsToAdd, $recordsThatExist, $record)
{
    if (count($recordsToAdd) > 0) {
        if (doesRecordExist($recordsToAdd, $record)) {
            return true;
        }
    }
    if (count($recordsThatExist) > 0) {
        if (doesRecordExist($recordsThatExist, $record)) {
            return true;
        }
    }
    return false;
}

/**
 * Parse Environment variables from DirectAdmin and split into key and value pairs
 */
function parseInput($input)
{
    $pairs = explode('&', $input);
    $results = array();
    foreach ($pairs as $pair) {
        if ($pair == "") continue;
        list($key, $value) = explode('=', $pair, 2);
        if ($key == "" || $value == "") continue;
        $object = new StdClass();
        $object->key = $key;
        $object->value = $value;
        $results[] = $object;
    }
    return $results;
}

/**
 * Fully qualify the record name
 */
function qualifyRecordName($name)
{
    global $domain;
    return trim($name . (substr($name, -1) !== '.' ? '.' . $domain : ''), '.');
}
