<?php

error_reporting(E_ALL);
require_once('vendor/autoload.php');

use Opis\JsonSchema\{
    Validator,
    ValidationResult,
    ValidationError,
    Schema
};

/**
 * Verify that the format of the config files in ./domains is correct
 */

$schema = Schema::fromJsonString(file_get_contents(__DIR__ . '/config_schema.json'));

$directory = __DIR__ . '/domains';
echo "\nVerifying config files in: $directory\n\n";
$scanned_directory = array_diff(scandir($directory), array('..', '.'));
$count = 0;

foreach ($scanned_directory as $settings_filename) {
    $count++;
    $extension = pathinfo($settings_filename, PATHINFO_EXTENSION);
    if ($extension !== 'json') {
        echo "Skipping file without .json extension: " . basename($settings_filename) . PHP_EOL;
        continue;
    }

    echo $count . ". Verify: " . basename($settings_filename) . "\n\n";
    $data = json_decode(file_get_contents($directory . "/" . $settings_filename));

    $result = '';
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            $result = 'JSON file decoded without errors';
            break;
        case JSON_ERROR_DEPTH:
            $result = 'Maximum stack depth exceeded';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $result = 'Underflow or the modes mismatch';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $result = 'Unexpected control character found';
            break;
        case JSON_ERROR_SYNTAX:
            $result = 'Syntax error, malformed JSON';
            break;
        case JSON_ERROR_UTF8:
            $result = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
        default:
            $result = 'Unknown error';
            break;
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "\tError decoding JSON file: " . $result . "\n";
        echo "\n\tResult: FAIL", PHP_EOL;
        echo "\n---------------------------------------------------------------------------------\n\n";
        continue;
    } else {
        echo "\t" . $result . "\n";
    }

    $validator = new Validator();

    /** @var ValidationResult $result */
    $result = $validator->schemaValidation($data, $schema);

    if ($result->isValid()) {
        echo "\tConfig file is valid", PHP_EOL;
        echo "\n\tResult: SUCCESS", PHP_EOL;
    } else {
        /** @var ValidationError $error */
        $error = $result->getFirstError();
        echo "\tConfig file is invalid", PHP_EOL;
        $error_node = $error->dataPointer();
        echo "\tNode containing the error: " . json_encode($error_node), PHP_EOL;
        $error_keyword = $error->keyword();
        if ($error_keyword == 'patternProperties') {
            $error_keyword = "JSON values must be boolean (true or false) without speechmarks";
        }

        if ($error_keyword == 'additionalProperties' && count(array_diff(array("proxy_record"), $error_node)) == 0) {
            $error_keyword = "The only domain records that Cloudflare can proxy are A, CNAME and AAAA.\n\tInvalid record type found in file.\n";
        }
        echo "\tError: ", $error_keyword, PHP_EOL;

        if (count($error->keywordArgs()) > 0) {
            echo "\n" . json_encode($error->keywordArgs()), PHP_EOL;
        }
        echo "\n\tResult: FAIL", PHP_EOL;
    }
    echo "\n---------------------------------------------------------------------------------\n\n";
}
