<?php

$DB_FILE = 'buskatoon.sqlite3';
$ROUTES_URL = 'https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Routes/FeatureServer/0/query?outFields=*&where=1%3D1&f=geojson';
$TRIPS_URL = 'https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Trips/FeatureServer/0/query?outFields=*&where=1%3D1&f=geojson';

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
$routes = json_decode(file_get_contents($ROUTES_URL));
foreach ($routes->features as $route) {
  // Add missing leading 0 that the XML feed seems to chop off
  $short_name = (string)$route->properties->route_short_name;
  if (strlen($short_name) == 1) $short_name = "0$short_name";

  $stmt->execute([
    ':id' => $route->properties->route_id,
    ':short_name' => $short_name,
    ':long_name' => $route->properties->route_long_name,
    ':color' => $route->properties->route_color
  ]);
}

$sql = 'INSERT INTO trips (id, route_id, headsign) VALUES (:id, :route_id, :headsign);';
$stmt = $db->prepare($sql);

$trips_url = $TRIPS_URL;
$limit = 1000;
$offset = 0;
do {
  $trips_page_url = "$trips_url&resultRecordCount=$limit&resultOffset=$offset";
  echo "Retrieving $trips_page_url\n";
  $trips = json_decode(file_get_contents($trips_page_url));

  foreach ($trips->features as $trip) {
    $stmt->execute([
      ':id' => $trip->properties->trip_id,
      ':route_id' => $trip->properties->route_id,
      ':headsign' => $trip->properties->trip_headsign
    ]);
  }

  $offset += $limit;
} while ($trips->properties->exceededTransferLimit ?? false);
