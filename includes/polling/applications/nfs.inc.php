<?php

use LibreNMS\Exceptions\JsonAppException;
use LibreNMS\RRD\RrdDefinition;

$name = 'nfs';

try {
    $returned = json_app_get($device, $name, 1);
} catch (JsonAppException $e) {
    echo PHP_EOL . $name . ':' . $e->getCode() . ':' . $e->getMessage() . PHP_EOL;

    update_application($app, $e->getCode() . ':' . $e->getMessage(), []); // Set empty metrics and error message
    return;
}

include 'includes/nfs-shared.inc.php';

$data = $returned['data'] ?? null;

// Add debug logging for `$returned` data to inspect the structure
error_log("NFS polling debug: " . json_encode($returned));

// Define RRD definitions
$rrd_def = RrdDefinition::make()
    ->addDataset('data', 'COUNTER');

$gauge_rrd_def = RrdDefinition::make()
    ->addDataset('data', 'GAUGE');

$metrics = [];

// Check if `$nfs_stat_keys` is set and is an array before iterating
if (isset($nfs_stat_keys) && is_array($nfs_stat_keys)) {
    foreach (array_keys($nfs_stat_keys) as $stat_name) {
        $rrd_name = ['app', $name, $app->app_id, $stat_name];
        
        // Check if stats data exists for each `$stat_name` before accessing
        $fields = ['data' => $returned['data']['stats'][$stat_name] ?? null];

        if (isset($gauge_stats[$stat_name])) {
            $rrd_def_to_use = $gauge_rrd_def;
        } else {
            $rrd_def_to_use = $rrd_def;
        }

        // Only update if the data for the current stat is available
        if ($fields['data'] !== null) {
            $tags = ['name' => $name, 'app_id' => $app->app_id, 'rrd_def' => $rrd_def_to_use, 'rrd_name' => $rrd_name];
            data_update($device, 'app', $tags, $fields);

            $metrics[$stat_name] = $returned['data']['stats'][$stat_name];
        } else {
            error_log("NFS polling warning: Missing data for stat '{$stat_name}'");
        }
    }
} else {
    error_log("NFS polling error: \$nfs_stat_keys is null or not an array");
}

// Check if `$returned['data']` contains necessary application data
$app_data = [
    'is_client' => $returned['data']['is_client'] ?? null,
    'is_server' => $returned['data']['is_server'] ?? null,
    'mounts' => $returned['data']['mounts'] ?? null,
    'mounted_by' => $returned['data']['mounted_by'] ?? null,
    'os' => $returned['data']['os'] ?? null,
];

$app->data = $app_data;
update_application($app, 'OK', $metrics);
