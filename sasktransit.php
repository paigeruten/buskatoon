<?php

require_once 'vendor/autoload.php';

use transit_realtime\FeedMessage;

$JSON_FILE = $argv[1] ?? 'vehicle_positions.json';

$vehicles = [];
if (file_exists($JSON_FILE)) {
    $vehicles = json_decode(file_get_contents($JSON_FILE), true);
}

$data = file_get_contents("http://apps2.saskatoon.ca/app/data/Vehicle/VehiclePositions.pb");
$feed = new FeedMessage();
$feed->parse($data);

foreach ($feed->getEntityList() as $entity) {
  if ($entity->hasVehicle()) {
    $id = $entity->getId();
    $vehicle = $entity->getVehicle();
    $position = $vehicle->getPosition();

    if (!isset($vehicles[$id])) {
        $vehicles[$id] = [];
    }
    if (empty($vehicles[$id]) || $vehicles[$id][0]['timestamp'] != $vehicle->getTimestamp()) {
        array_unshift($vehicles[$id], [
            'latitude' => $position->getLatitude(),
            'longitude' => $position->getLongitude(),
            'bearing' => $position->getBearing() ?? 0,
            'timestamp' => $vehicle->getTimestamp(),
        ]);
        $vehicles[$id] = array_slice($vehicles[$id], 0, 5);
    }
  }
}

$json = json_encode($vehicles);
file_put_contents($JSON_FILE, $json);
