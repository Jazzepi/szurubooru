#!/usr/bin/env bash

c1=\"\";while :;do c2=$(find html js css img -type f -and -not -iname '*autogen*'|sort|xargs cat|md5sum);[[ $c1 != $c2 ]]&&npm run build -- --debug --no-vendor-js;c1=$c2;sleep 1;done
