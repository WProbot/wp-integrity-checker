<?php

/**
 * Verify WP Core files md5 checksums, outside WordPress.
 * Use this script to verify md5 checksums of WordPress core files.
 */
if (version_compare(PHP_VERSION, '5.6.30', '<')) {
    die('You are using PHP Version: ' . PHP_VERSION . '.
        I think you should use a higher PHP version, at least 5.6.30!
        (change the PHP version check if you must...) ');
}

/**
 * Put this file in the your WordPress root folder, leave ABSPATH
 * defined  as './'.
 */
if (!file_exists(__DIR__."/config.php")) {
    die('No config.php, copy config_example.php to config.php and change settings there');
}
require __DIR__ . "/config.php";

foreach ($config['CHECK_PATH'] as $path) {
    checkWordpress($path, $config);
}

function checkWordpress($path, $config)
{
    $versionPath = $path . '/wp-includes/version.php';
    if (file_exists($versionPath)) {
        require ($versionPath);
    } else {
        die("Path {$versionPath} does not exist");
    }
    $wp_locale = isset($wp_local_package) ? $wp_local_package : 'en_US';
    $apiurl = 'https://api.wordpress.org/core/checksums/1.0/?version=' . $wp_version . '&locale=' . $wp_locale;

    $content = file_get_contents($apiurl);
    if (!$content) {
        die('WP API returned empty');
    }

    $json = json_decode($content);
    if (!$json) {
        die('Wrong output of wordpress API');
    }

    $checksums = $json->checksums;

    foreach ($checksums as $file => $checksum) {
        checkWPFile($file, $checksum, $path, $config);
    }
}

function checkWPFile($file, $checksum, $path, $config)
{
    $file_path = $path . "/" . $file;

    if (file_exists($file_path)) {
        if (md5_file($file_path) !== $checksum) {
            changedWPAlarm($file_path, $config);
        }
    }
}

function changedWPAlarm($file_path, $config)
{
    $message = "Checksum for " . $file_path . " does not match!" . PHP_EOL;
    echo $message;
    sendToSlack($message, $config);
}

function sendToSlack($message, $config)
{
    if (!function_exists('curl_init')) {
        die('Missing curl extension');
    }
    $ch = curl_init("https://slack.com/api/chat.postMessage");
    $data = http_build_query([
        "token" => $config['SLACK_TOKEN'],
        "channel" => $config['SLACK_CHANNEL'],
        "text" => $message,
        "username" => "WP Integrity Checker",
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result) {
        $result_decoded = json_decode($result, true);
    } else {
        return false;
    }

    return $result_decoded['ok'];
}
