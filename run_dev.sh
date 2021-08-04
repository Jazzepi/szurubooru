#!/bin/bash

mkdir -p /tmp/nginx/

pushd ./server
source python_modules/bin/activate
gunicorn szurubooru.facade:app --reload -b 127.0.0.1:6666 &
nginx -p . -c nginx.dev.conf &
popd

pushd ./client
pwd
npm run watch &
popd

wait
