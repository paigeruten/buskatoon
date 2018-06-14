<?php

$DB_FILE = 'sasktransit.sqlite3';

if (file_exists($DB_FILE)) {
  unlink($DB_FILE);
}

$db = new PDO("sqlite:$DB_FILE");

$db->exec('
  CREATE TABLE routes (
    id INTEGER PRIMARY KEY,
    short_name TEXT,
    long_name TEXT
  );
');

$db->exec('
  CREATE TABLE trips (
    id INTEGER PRIMARY KEY,
    route_id INTEGER,
    headsign TEXT
  );
');

$sql = 'INSERT INTO routes (id, short_name, long_name) VALUES (:id, :short_name, :long_name);';
$stmt = $db->prepare($sql);
$routes_file = fopen("data/routes.txt", "r");
$routes_header = fgetcsv($routes_file, 1000, ",");
$ROUTE_ROUTE_ID = array_search('route_id', $routes_header);
$ROUTE_SHORT_NAME = array_search('route_short_name', $routes_header);
$ROUTE_LONG_NAME = array_search('route_long_name', $routes_header);
while (($row = fgetcsv($routes_file, 1000, ",")) !== FALSE) {
  $stmt->execute([
    ':id' => $row[$ROUTE_ROUTE_ID],
    ':short_name' => $row[$ROUTE_SHORT_NAME],
    ':long_name' => $row[$ROUTE_LONG_NAME]
  ]);
}
fclose($routes_file);

$sql = 'INSERT INTO trips (id, route_id, headsign) VALUES (:id, :route_id, :headsign);';
$stmt = $db->prepare($sql);
$trips_file = fopen("data/trips.txt", "r");
$trips_header = fgetcsv($trips_file, 1000, ",");
$TRIP_TRIP_ID = array_search('trip_id', $trips_header);
$TRIP_ROUTE_ID = array_search('route_id', $trips_header);
$TRIP_HEADSIGN = array_search('trip_headsign', $trips_header);
while (($row = fgetcsv($trips_file, 1000, ",")) !== FALSE) {
  $stmt->execute([
    ':id' => $row[$TRIP_TRIP_ID],
    ':route_id' => $row[$TRIP_ROUTE_ID],
    ':headsign' => $row[$TRIP_HEADSIGN]
  ]);
}
fclose($trips_file);
