#!/bin/bash
set -euxo pipefail

echo "rsyncing files to /boruu on the remote server"

#export MOUNT_DATA='/mnt/booru_storage/MOUNTS/data'
#export MOUNT_SQL='/mnt/booru_storage/MOUNTS/sql'
#export PORT='9999' Not needed, using default

docker compose build

# Eventually add --delete-after to make it delete old files in the target directories
# /home/jewel/.ssh/digital_ocean_id
rsync -rvc \
--copy-links \
--progress \
--stats \
--update \
--exclude '*node_modules*' \
--exclude '*.swp' \
--exclude '*__pycache__*' \
--exclude '*.git*' \
--exclude '*.idea*' \
--exclude 'config.yaml.dist' \
--exclude deploy.sh \
. \
root@booru:~/boruu

docker save whitekitten-booru-client:latest | gzip | DOCKER_HOST=ssh://booru docker load
docker save whitekitten-booru-server:latest | gzip | DOCKER_HOST=ssh://booru docker load
ssh booru -t 'docker compose -f /root/boruu/docker-compose.yml --env-file /dev/null up --force-recreate -d --no-build'