#!/usr/bin/env bash

set -o errexit
set -o pipefail
set -o nounset

SCRIPT_PATH="$(realpath ${BASH_SOURCE[0]})"
PROJECT_ROOT="$(realpath $(dirname $SCRIPT_PATH)/../)"

cd $PROJECT_ROOT

readonly FILES="
bin
inc
src
vendor
composer.json
composer.lock
LICENSE
README.md
"

function download {
  echo "Downloading phar-composer..."
  curl -sL https://github.com/clue/phar-composer/releases/download/v1.0.0/phar-composer.phar -O phar-composer.phar
}

function copy {
  local dst="$1"

  mkdir -p $dst

  for entry in $FILES; do
    cp -r $entry $dst
  done
}

function main {
  [[ -f phar-composer.phar ]] || download

  copy tmp/datashot

  php phar-composer.phar build ./tmp/datashot/
  rm -rf ./tmp/datashot/

  mv datashot.phar datashot
}

main
