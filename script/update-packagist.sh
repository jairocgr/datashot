#!/usr/bin/env bash

source .env

curl -XPOST -H'content-type:application/json' \
  https://packagist.org/api/update-package\?username=jairocgr\&apiToken=$API_TOKEN \
  -d'{"repository":{"url":"https://packagist.org/packages/jairocgr/datashot"}}'
