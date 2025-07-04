#!/bin/bash
set -exu pipefail

cd "$(dirname "$0")"

mkdir -p /tmp/nginx/

cd ./server
gunicorn szurubooru.facade:app --reload -b 127.0.0.1:6666 2>&1 | sed 's/^/GUNICORN: /' &
nginx -p . -c nginx.dev.conf 2>&1 | sed 's/^/NGINX /' &
cd ..

cd ./client
pwd
npm run watch 2>&1 | sed 's/^/NPM /' &
cd ..

wait
