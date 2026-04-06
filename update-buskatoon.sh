#!/bin/bash

MAX_RUNTIME=50

while [ $SECONDS -lt $MAX_RUNTIME ]; do
    START_TIME=$SECONDS
    php ~/bin/buskatoon.php /srv/www/buskatoon.ca/vehicle_positions.json

    ELAPSED=$((SECONDS - START_TIME))
    if [ $ELAPSED -lt 10 ]; then
        SLEEP_TIME=$((10 - ELAPSED))
        
        if [ $((SECONDS + SLEEP_TIME)) -lt $MAX_RUNTIME ]; then
            sleep $SLEEP_TIME
        fi
    fi
done
