<?php

$DB_FILE = 'buskatoon.sqlite3';
$ROUTES_URL = 'http://opendata-saskatoon.cloudapp.net:8080/v1/SaskatoonOpenDataCatalogueBeta/TransitRoutes/';
$TRIPS_URL = 'http://opendata-saskatoon.cloudapp.net:8080/v1/SaskatoonOpenDataCatalogueBeta/TransitTrips/';

if (file_exists($DB_FILE)) {
  unlink($DB_FILE);
}

$db = new PDO("sqlite:$DB_FILE");
$db->query('PRAGMA synchronous = OFF;');

$db->exec('
  CREATE TABLE routes (
    id INTEGER PRIMARY KEY,
    short_name TEXT,
    long_name TEXT,
    color TEXT
  );
');

$db->exec('
  CREATE TABLE trips (
    id INTEGER PRIMARY KEY,
    route_id INTEGER,
    headsign TEXT
  );
');

$sql = 'INSERT INTO routes (id, short_name, long_name, color) VALUES (:id, :short_name, :long_name, :color);';
$stmt = $db->prepare($sql);
echo "Retrieving $ROUTES_URL\n";
$routes = simplexml_load_file($ROUTES_URL);
foreach ($routes->entry as $route) {
  $route = $route->content->children('m', true)->properties->children('d', true);

  // Add missing leading 0 that the XML feed seems to chop off
  $short_name = $route->route_short_name;
  if (strlen($short_name) == 1) $short_name = "0$short_name";

  $stmt->execute([
    ':id' => $route->route_id,
    ':short_name' => $short_name,
    ':long_name' => $route->route_long_name,
    ':color' => $route->route_color
  ]);
}

$sql = 'INSERT INTO trips (id, route_id, headsign) VALUES (:id, :route_id, :headsign);';
$stmt = $db->prepare($sql);

$trips_url = $TRIPS_URL;
do {
  echo "Retrieving $trips_url\n";
  $trips = simplexml_load_file($trips_url);
  foreach($trips->getDocNamespaces() as $prefix => $namespace) {
      if (empty($prefix)) $prefix = "global";
      $trips->registerXPathNamespace($prefix, $namespace);
  }

  foreach ($trips->entry as $trip) {
    $trip = $trip->content->children('m', true)->properties->children('d', true);

    $stmt->execute([
      ':id' => $trip->trip_id,
      ':route_id' => $trip->route_id,
      ':headsign' => $trip->trip_headsign
    ]);
  }

  $next_link = $trips->xpath("global:link[@rel='next']");
  $trips_url = empty($next_link) ? false : (string)$next_link[0]['href'];
} while ($trips_url);
