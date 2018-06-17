# Buskatoon

It's a map of Saskatoon that shows where all the buses are at.

Here it is: https://buskatoon.ca/

(Maybe inspired by: http://totransit.ca/)

## Run locally

Requires: PHP7 + [composer](https://getcomposer.org/)

First, install PHP dependencies and create the trips/routes database:

```bash
$ composer install
$ php import_data.php
```

`php buskatoon.php` will pull the latest `VehiclePositions.pb` data and update `vehicle_positions.json` with it. Use `run.sh` to have it automatically run every 10 seconds:

```bash
$ ./run.sh
```

Finally, open `index.html` in your browser. The bus positions on the map should automatically update as the script pulls in new location data.

```bash
$ open index.html
```

## The data

Each bus reports its GPS location every 20-30 seconds. The [Saskatoon Transit Real Time Data Feed](https://www.saskatoon.ca/moving-around/transit/open-data-saskatoon-transit) makes these location reports available to us at [apps2.saskatoon.ca/app/data/Vehicle/VehiclePositions.pb](http://apps2.saskatoon.ca/app/data/Vehicle/VehiclePositions.pb). This feed also seems to only update every 20-30 seconds itself, so the bus locations we get are always 20-30 seconds out of date.

In addition to latitude and longitude, we also get the bearing (direction the bus is facing, which seems to always be a multiple of 45) and the trip id. The trip id can be looked up in the [Saskatoon Open Data Catalogue](http://opendata-saskatoon.cloudapp.net/DataBrowser/SaskatoonOpenDataCatalogueBeta/TransitTrips#param=NOFILTER--DataView--Results) to get the bus's headsign (like "Centre Mall via Lorne") and route id. The route id can then be [looked up](http://opendata-saskatoon.cloudapp.net/DataBrowser/SaskatoonOpenDataCatalogueBeta/TransitRoutes#param=NOFILTER--DataView--Results) to get the route number (like "8") and route name (like "8th Street / City Centre").

## TODO

* Display (and maybe zoom into) your location
* Show a timer that estimates how long till the next update (with a note about how data tends to be 20 seconds out of date)
* Figure out better way of showing the buss's's location history. Maybe only show it when clicking on a bus, or fade out the colours of each line
* Show your current location
* Better browser compatibility?
* Use webpack or something instead of loading JS libraries from various CDNs?
* Do something about handling outages. All the buses stopped reporting their positions for ~15 minutes today. Maybe draw out of date buses as greyed-out, or show a message when an outage is detected. Make sure not to draw the polyline when there are big time gaps in data.
