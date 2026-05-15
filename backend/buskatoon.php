<?php

error_reporting(E_ALL & ~E_DEPRECATED);

require_once 'vendor/autoload.php';

use transit_realtime\FeedMessage;

$SCRIPT_PATH = realpath(dirname(__FILE__));

$DB_FILE = "$SCRIPT_PATH/buskatoon.sqlite3";
$JSON_FILE = $argv[1] ?? "$SCRIPT_PATH/vehicle_positions.json";
$OUTAGE_LOG_FILE = "$SCRIPT_PATH/outages.log";

function fetch_url($url, $max_retries = 4) {
  // Fast fail on our end to prevent a much longer timeout on their end that often happens
  $timeout_seconds = 2; 

  $options = [
    'http' => [
      'method' => "GET",
      'header' => "User-Agent: Buskatoon/1.0\r\nAccept: */*\r\n",
      'timeout' => $timeout_seconds,
      'ignore_errors' => false
    ],
    'ssl' => [
      'timeout' => $timeout_seconds,
      'verify_peer' => true,
      'verify_peer_name' => true,
    ]
  ];

  $context = stream_context_create($options);

  for ($i = 0; $i < $max_retries; $i++) {
    $result = @file_get_contents($url, false, $context);

    if ($result !== false) {
      return $result;
    }

    usleep(200000);
  }

  return false;
}

$current_outage = 0;
$vehicles = [];
if (file_exists($JSON_FILE)) {
  $data = json_decode(file_get_contents($JSON_FILE), true);
  $current_outage = $data['outage'] ?? 0;
  $vehicles = $data['vehicles'] ?? [];
}

$data = fetch_url("https://saskprdtmgtfs.sasktrpcloud.com/TMGTFSRealTimeWebService/Vehicle/VehiclePositions.pb");
if ($data === false) {
  die("Failed to retrieve VehiclePositions.pb\n");
}
$feed = new FeedMessage();
$feed->parse($data);

if (empty($feed->getEntityList()) && !$current_outage) {
  $current_outage = time();
} elseif (!empty($feed->getEntityList()) && $current_outage) {
  file_put_contents(
    $OUTAGE_LOG_FILE,
    "$current_outage " . time() . "\n",
    FILE_APPEND
  );
  $current_outage = 0;
}

$db = new PDO("sqlite:$DB_FILE");

foreach ($feed->getEntityList() as $entity) {
  if ($entity->hasVehicle()) {
    $id = $entity->getId();
    $vehicle = $entity->getVehicle();
    $position = $vehicle->getPosition();

    $trip_id = $vehicle->getTrip()->getTripId();

    $sql = 'SELECT * FROM trips, routes WHERE trips.trip_id = :trip_id AND trips.route_id = routes.route_id';
    $stmt = $db->prepare($sql);
    $stmt->execute([':trip_id' => $trip_id]);
    $result = $stmt->fetch();
    if ($result) {
      $route = [
        'short_name' => $result['short_name'],
        'long_name' => $result['long_name'],
        'headsign' => $result['headsign'],
        'color' => $result['color'],
        'shape_id' => $result['shape_id'],
        'trip_id' => $result['trip_id'],
      ];
    } else {
      $route = null;
    }

    if (!isset($vehicles[$id])) {
      $vehicles[$id] = [];
    }
    if (empty($vehicles[$id]) || $vehicles[$id][0]['timestamp'] != $vehicle->getTimestamp()) {
      array_unshift($vehicles[$id], [
        'route' => $route,
        'latitude' => $position->getLatitude(),
        'longitude' => $position->getLongitude(),
        'bearing' => $position->getBearing() ?? 0,
        'speed' => $position->getSpeed(),
        'timestamp' => $vehicle->getTimestamp(),
      ]);
      $vehicles[$id] = array_slice($vehicles[$id], 0, 5);
      for ($i = 1; $i < count($vehicles[$id]); $i++) {
        unset($vehicles[$id][$i]['route']);
        if ($vehicles[$id][$i-1]['timestamp'] - $vehicles[$id][$i]['timestamp'] > 150) {
          $vehicles[$id] = array_slice($vehicles[$id], 0, $i);
          break;
        }
      }
    }
  }
}

foreach (array_keys($vehicles) as $id) {
  if ($vehicles[$id][0]['timestamp'] < time() - 1800) {
    unset($vehicles[$id]);
  }
}

$json = json_encode([
  'outage' => $current_outage,
  'vehicles' => $vehicles
]);
file_put_contents($JSON_FILE, $json);
