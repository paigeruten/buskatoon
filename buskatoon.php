<?php

require_once 'vendor/autoload.php';

use transit_realtime\FeedMessage;

$SCRIPT_PATH = realpath(dirname(__FILE__));

$DB_FILE = "$SCRIPT_PATH/buskatoon.sqlite3";
$JSON_FILE = $argv[1] ?? "$SCRIPT_PATH/vehicle_positions.json";

$vehicles = [];
if (file_exists($JSON_FILE)) {
  $vehicles = json_decode(file_get_contents($JSON_FILE), true);
}

$data = file_get_contents("http://apps2.saskatoon.ca/app/data/Vehicle/VehiclePositions.pb");
$feed = new FeedMessage();
$feed->parse($data);

$db = new PDO("sqlite:$DB_FILE");

foreach ($feed->getEntityList() as $entity) {
  if ($entity->hasVehicle()) {
    $id = $entity->getId();
    $vehicle = $entity->getVehicle();
    $position = $vehicle->getPosition();

    $trip_id = $vehicle->getTrip()->getTripId();

    $sql = 'SELECT * FROM trips, routes WHERE trips.id = :trip_id AND trips.route_id = routes.id';
    $stmt = $db->prepare($sql);
    $stmt->execute([':trip_id' => $trip_id]);
    $result = $stmt->fetch();
    if ($result) {
      $route = [
        'short_name' => $result['short_name'],
        'long_name' => $result['long_name'],
        'headsign' => $result['headsign']
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
        'timestamp' => $vehicle->getTimestamp(),
      ]);
      $vehicles[$id] = array_slice($vehicles[$id], 0, 5);
    }
  }
}

foreach (array_keys($vehicles) as $id) {
  if ($vehicles[$id][0]['timestamp'] < time() - 150) {
    unset($vehicles[$id]);
  }
}

$json = json_encode($vehicles);
file_put_contents($JSON_FILE, $json);
