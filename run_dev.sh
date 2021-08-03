#!/bin/bash

mkdir -p /tmp/nginx/

fuser -k 6666/tcp & fuser -k 8001/tcp

cd ./server
. python_modules/bin/activate
gunicorn szurubooru.facade:app --log-level 'debug' --reload -b 127.0.0.1:6666 &
nginx -p . -c nginx.dev.conf &
cd ..

cd ./client
pwd
npm run watch &
cd ..

wait