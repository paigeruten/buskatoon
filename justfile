deploy:
    ./deploy.sh

serve:
    open http://localhost:8000
    cd public && python3 -m http.server 8000

run:
    cd backend && while true ; do php buskatoon.php ../public/vehicle_positions.json & sleep 10; done

dep:
    cd backend && composer install

update:
    cd backend && php import_data.php ../public/shapes.json && rm -f ../public/vehicle_positions.json

