<?php

/**
 * Load Cloudflare PHP API
 */
require_once('vendor/autoload.php');
$key = new \Cloudflare\API\Auth\APIKey($cloudflare_email, $cloudflare_api_key);
$adapter = new Cloudflare\API\Adapter\Guzzle($key);
$zones = new \Cloudflare\API\Endpoints\Zones($adapter);

logMessage("------------------ dns_write_post.sh ------------------");

$domain = getenv('DOMAIN');

// Retrieve DNS Records from Environment Variables
$a = getenv('A');
$cname = getenv('CNAME');
$mx = getenv('MX');
$ns = getenv('NS');
$ptr = getenv('PTR');
$txt = getenv('TXT');
$spf = getenv('SPF');
$aaaa = getenv('AAAA');
$srv = getenv('SRV');

/**
 * Unused environment variables
 * 
 * @todo Use DirectAdmin TTL values - at present this script uses the Cloudflare defaults
 */
// $mxfull = getenv('MX_FULL');
// $serial = getenv('SERIAL');
// $srv_email = getenv('EMAIL');
// $domainIP = getenv('DOMAIN_IP');
// $serverIP = getenv('SERVER_IP');
// $ds = getenv('DS');
// $aTTL = getenv('A_TIME');
// $cnameTTL = getenv('CNAME_TIME');
// $nsTTL = getenv('NS_TIME');
// $ptrTTL = getenv('PTR_TIME');
// $dsTTL = getenv('DS_TIME');
// $spfTTL = getenv('SPF_TIME');
// $aaaaTTL = getenv('AAAA_TIME');
// $srvTTL = getenv('SRV_TIME');

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
 * Load existing DNS records for the domain
 */
$dns = new \Cloudflare\API\Endpoints\DNS($adapter);
$existingRecords = $dns->listRecords($zoneID)->result;

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
            'type' => 'A',
            'name' => qualifyRecordName($value->key),
            'content' => $value->value
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
            'type' => 'TXT',
            'name' => qualifyRecordName($value->key),
            'content' => trim(preg_replace(array('/"\s+"/', '/"/'), array('', ''), trim($value->value, '()'))),
        );
        if (doesRecordExist($existingRecords, $record) === false) {
            $recordsToAdd[] = $record;
        } else {
            $recordsThatExist[] = $record;
        }
    }
}

// parse MX records
$output = parseInput($mx);
if (count($output) > 0) {
    foreach ($output as $value) {
        $record = (object) array(
            'type' => 'MX',
            'name' => $domain,
            'content' => qualifyRecordName($value->key),
            'priority' => $value->value
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
            'type' => 'CNAME',
            'name' => qualifyRecordName($value->key),
            'content' => $value->value
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
            'type' => 'PTR',
            'name' => qualifyRecordName($value->key),
            'content' => $value->value
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
            'type' => 'AAAA',
            'name' => qualifyRecordName($value->key),
            'content' => $value->value
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
        preg_match('/^(.*)\._(tcp|udp|tls)(.*)$/', $fullSRVname, $srv_match);

        $record = (object) array(
            'type' => 'SRV',
            'name' => $fullSRVname,
            'priority' => $parsedValue[1],
            'content' => $parsedValue[4],
            'data' => array(
                'weight' => (int) $parsedValue[2],
                'port' => (int) $parsedValue[3],
                'target' => $parsedValue[4],
                'proto' => '_' . $srv_match[2],
                'service' => $srv_match[1],
                'priority' => (int) $parsedValue[1],
            ),
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
    $proxied = false;
    $ttl = 0; // use default TTL
    try {
        $success = $dns->addRecord($zoneID, $record->type, $record->name, $record->content, $ttl, $proxied, $priority, $data);
        logMessage('Add Record: ' . $record->name . "\t" . $record->type . "\t" . $record->content . "\t" . ($success == true ? 'SUCCESSFUL' : 'FAILED'));
    } catch (\GuzzleHttp\Exception\ClientException $e) {
        logMessage('Add Record: ' . $record->name . "\t" . $record->type . "\t" . $record->content . "\t" . 'FAILED');
        logMessage($e->getResponse()->getBody()->getContents());
    }
}

/**
 * Log a message to the php error log
 */
function logMessage($message)
{
    global $log_messages, $log_to_file, $log_filename;
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
    if (count($records) == 0) {
        return false;
    }
    foreach ($records as $compare) {
        if ($record->type == 'SRV') {
            $compare_data = (object) $compare->data;
            $record_data = (object) $record->data;
            if (
                $compare->type == $record->type &&
                $compare->name == $record->name &&
                $compare_data->weight == $record_data->weight &&
                $compare_data->target == $record_data->target &&
                $compare_data->proto == $record_data->proto &&
                $compare_data->service == $record_data->service &&
                $compare_data->priority == $record_data->priority &&
                $compare_data->port == $record_data->port
            ) {
                return true;
            }
        } else {
            if ($compare->type == $record->type && $compare->name == $record->name && $compare->content == $record->content) {
                if ($record->type == 'MX' && $compare->priority != $record->priority) {
                    continue;
                }
                return true;
            }
        }
    }
    return false;
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