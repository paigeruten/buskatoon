rm -rf data
curl -O http://apps2.saskatoon.ca/app/data/google_transit.zip &&
  unzip -d data google_transit.zip &&
  php import_csv.php &&
  rm -f /srv/www/buskatoon.ca/vehicle_positions.json

