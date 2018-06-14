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

$routes_file = fopen("data/routes.txt", "r");
$routes_header = fgetcsv($routes_file, 1000, ",");
$routes = [];
while (($row = fgetcsv($routes_file, 1000, ",")) !== FALSE) {
  $routes[] = $row;
}
fclose($routes_file);

$trips_file = fopen("data/trips.txt", "r");
$trips_header = fgetcsv($trips_file, 1000, ",");
$trips = [];
while (($row = fgetcsv($trips_file, 1000, ",")) !== FALSE) {
  $trips[] = $row;
}
fclose($trips_file);

$ROUTE_ROUTE_ID = array_search('route_id', $routes_header);
$ROUTE_SHORT_NAME = array_search('route_short_name', $routes_header);
$ROUTE_LONG_NAME = array_search('route_long_name', $routes_header);

$TRIP_TRIP_ID = array_search('trip_id', $trips_header);
$TRIP_ROUTE_ID = array_search('route_id', $trips_header);
$TRIP_HEADSIGN = array_search('trip_headsign', $trips_header);

$routes_by_id = [];
foreach ($routes as $route) {
  $routes_by_id[$route[$ROUTE_ROUTE_ID]] = $route;
}

$routes_by_trip_id = [];
foreach ($trips as $trip) {
  $routes_by_trip_id[$trip[$TRIP_TRIP_ID]] = $routes_by_id[$trip[$TRIP_ROUTE_ID]];
}

foreach ($feed->getEntityList() as $entity) {
  if ($entity->hasVehicle()) {
    $id = $entity->getId();
    $vehicle = $entity->getVehicle();
    $position = $vehicle->getPosition();

    $trip_id = $vehicle->getTrip()->getTripId();
    $route = $routes_by_trip_id[$trip_id] ?? [];

    if (!isset($vehicles[$id])) {
      $vehicles[$id] = [];
    }
    if (empty($vehicles[$id]) || $vehicles[$id][0]['timestamp'] != $vehicle->getTimestamp()) {
      array_unshift($vehicles[$id], [
        'route' => $route[$ROUTE_SHORT_NAME] ?? null,
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
  if ($vehicles[$id][0]['timestamp'] < time() - 300) {
    unset($vehicles[$id]);
  }
}

$json = json_encode($vehicles);
file_put_contents($JSON_FILE, $json);
