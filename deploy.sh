#!/bin/bash

deploy_folder() {
    local source="$1"
    local dest="$2"
    shift 2
    local extra_args=("$@")

    echo ""
    echo "---> Deploying from '$source' to '$dest'..."

    local orphans=$(rsync -rtvznc "${extra_args[@]}" --delete --out-format='%n' "$source" "$dest" | grep -i "^deleting")
    if [ -n "$orphans" ]; then
        echo "!! These files exist on the server but not locally:"
        echo "$orphans" | sed 's/^deleting /  - /'
        echo ""
    fi

    rsync -rtvzc "${extra_args[@]}" --chmod=D755,F644 --out-format='%n' "$source" "$dest" | grep --line-buffered -v '/$'
}

SERVER="paige@146.190.241.28"

deploy_folder "./public/" "$SERVER:/var/www/buskatoon.ca/" \
    --exclude "vehicle_positions.json" \
    --exclude "shapes.json"

deploy_folder "./backend/" "$SERVER:/home/paige/buskatoon/" \
    --exclude "/vendor" \
    --exclude "*.sqlite3"

echo ""
echo "---> SSHing into server..."

ssh "$SERVER" << 'EOF'
    cd ~/buskatoon

    echo ""
    echo "---> Installing PHP dependencies..."
    composer install --no-progress 2>&1

    echo ""
    echo "---> Checking crontab..."
    crontab -l 2>/dev/null | grep -v '^#' > /tmp/cur_crontab.txt
    if ! cmp -s /tmp/cur_crontab.txt cron/crontab.txt; then 
        echo "!! Crontab needs to be updated"
    else 
        echo "Crontab up to date"
    fi
EOF
