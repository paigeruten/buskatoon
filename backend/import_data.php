<?php

function fetch_url_or_exit($url, $max_retries = 3) {
  echo "Retrieving $url\n";

  $options = [
    'http' => [
      'method' => "GET",
      'header' => "User-Agent: Buskatoon/1.0\r\n",
      'timeout' => 30,
      'ignore_errors' => true
    ],
    'ssl' => [
      'timeout' => 10,
      'verify_peer' => true,
      'verify_peer_name' => true,
    ]
  ];

  $context = stream_context_create($options);
  $attempts = 0;

  while ($attempts < $max_retries) {
    if ($attempts > 0) {
      echo "  Retrying (attempt #" . ($attempts + 1) . ")\n";
    }
    $result = @file_get_contents($url, false, $context);

    if ($result !== false) {
      return $result;
    }

    echo "  Fetch failed\n";

    $attempts++;
    if ($attempts < $max_retries) {
      sleep(2 * $attempts);
    }
  }

  echo "  Giving up after $attempts failed attempts\n";
  exit(1);
}

// --------------------------------------------
// Import Trips and Routes into SQLite database
// --------------------------------------------

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
    route_id INTEGER PRIMARY KEY,
    short_name TEXT,
    long_name TEXT,
    color TEXT
  );
');

$db->exec('
  CREATE TABLE trips (
    trip_id INTEGER PRIMARY KEY,
    route_id INTEGER,
    shape_id INTEGER,
    headsign TEXT
  );
');

$sql = 'INSERT INTO routes (route_id, short_name, long_name, color) VALUES (:route_id, :short_name, :long_name, :color);';
$stmt = $db->prepare($sql);
$routes = json_decode(fetch_url_or_exit($ROUTES_URL));
foreach ($routes->features as $route) {
  // Add missing leading 0 that the XML feed seems to chop off
  $short_name = (string)$route->properties->route_short_name;
  if (strlen($short_name) == 1) $short_name = "0$short_name";

  $stmt->execute([
    ':route_id' => $route->properties->route_id,
    ':short_name' => $short_name,
    ':long_name' => $route->properties->route_long_name,
    ':color' => $route->properties->route_color
  ]);
}

$sql = 'INSERT INTO trips (trip_id, route_id, shape_id, headsign) VALUES (:trip_id, :route_id, :shape_id, :headsign);';
$stmt = $db->prepare($sql);

$trips_url = $TRIPS_URL;
$limit = 1000;
$offset = 0;
do {
  $trips_page_url = "$trips_url&resultRecordCount=$limit&resultOffset=$offset";
  $trips = json_decode(fetch_url_or_exit($trips_page_url));

  foreach ($trips->features as $trip) {
    $stmt->execute([
      ':trip_id' => $trip->properties->trip_id,
      ':route_id' => $trip->properties->route_id,
      ':shape_id' => $trip->properties->shape_id,
      ':headsign' => $trip->properties->trip_headsign
    ]);
  }

  $offset += $limit;
} while ($trips->properties->exceededTransferLimit ?? false);

// ---------------------------------------------
// Import Shapes and Stops into static JSON file
// ---------------------------------------------

$SHAPES_FILE = $argv[1] ?? 'shapes.json';
$SHAPES_URL = 'https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Shapes/FeatureServer/0/query?outFields=*&where=1%3D1&f=geojson';
$STOPS_URL = 'https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Stops/FeatureServer/0/query?outFields=stop_id,stop_lon,stop_lat&where=1%3D1&f=geojson';
$STOP_TIMES_URL = 'https://services2.arcgis.com/eJz9754Ox6TaFSC2/arcgis/rest/services/Transit_Stop_Times/FeatureServer/0/query?outFields=trip_id,stop_id&where=1%3D1&f=geojson';

$shapes_url = $SHAPES_URL;
$limit = 1000;
$offset = 0;
$shapes = [];
do {
  $shapes_page_url = "$shapes_url&resultRecordCount=$limit&resultOffset=$offset";
  $result = json_decode(fetch_url_or_exit($shapes_page_url));

  foreach ($result->features as $shape) {
    if (!isset($shapes[$shape->properties->shape_id])) {
      $shapes[$shape->properties->shape_id] = [];
    }
    $shapes[$shape->properties->shape_id][] = [
      'latitude' => $shape->properties->shape_pt_lat,
      'longitude' => $shape->properties->shape_pt_lon,
      'seq' => $shape->properties->shape_pt_sequence,
    ];
  }

  $offset += $limit;
} while ($result->properties->exceededTransferLimit ?? false);

foreach (array_keys($shapes) as $shape_id) {
  usort($shapes[$shape_id], function ($a, $b) { return $a['seq'] <=> $b['seq']; });
  $shapes[$shape_id] = array_map(function ($shape) { return [$shape['longitude'], $shape['latitude']]; }, $shapes[$shape_id]);
}

$stops_url = $STOPS_URL;
$limit = 1000;
$offset = 0;
$stops = [];
do {
  $stops_page_url = "$stops_url&resultRecordCount=$limit&resultOffset=$offset";
  $result = json_decode(fetch_url_or_exit($stops_page_url));

  foreach ($result->features as $stop) {
    $stops[$stop->properties->stop_id] = [$stop->properties->stop_lon, $stop->properties->stop_lat];
  }

  $offset += $limit;
} while ($result->properties->exceededTransferLimit ?? false);

$stop_times_url = $STOP_TIMES_URL;
$limit = 1000;
$offset = 0;
$stops_by_trip_id = [];
do {
  $stop_times_page_url = "$stop_times_url&resultRecordCount=$limit&resultOffset=$offset";
  $result = json_decode(fetch_url_or_exit($stop_times_page_url));

  foreach ($result->features as $stop_time) {
    if (!isset($stops_by_trip_id[$stop_time->properties->trip_id])) {
      $stops_by_trip_id[$stop_time->properties->trip_id] = [];
    }
    $stops_by_trip_id[$stop_time->properties->trip_id][] = $stop_time->properties->stop_id;
  }

  $offset += $limit;
} while ($result->properties->exceededTransferLimit ?? false);

file_put_contents($SHAPES_FILE, json_encode([
  'shapes_by_id' => $shapes,
  'stops_by_id' => $stops,
  'stops_by_trip_id' => $stops_by_trip_id,
]));
