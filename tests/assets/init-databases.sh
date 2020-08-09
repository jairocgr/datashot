#!/usr/bin/env bash

# Exit immediately if something returns a non-zero status
set -o errexit

# If set, the return value of a pipeline is the value of the last (rightmost)
# command to exit with a non-zero status, or zero if all commands in the
# pipeline exit successfully. This option is disabled by default.
set -o pipefail

# Exit your script if you try to use an uninitialised variable
set -o nounset

SCRIPT_PATH="$(realpath ${BASH_SOURCE[0]})"
PROJECT_ROOT="$(realpath $(dirname $SCRIPT_PATH)/../../)"

function load_env {
  if ! [[ -f .env ]]; then
    cp .env.example .env
  fi

  set -a
  . .env
  set +a
}

function hide_passwd_warn {
  grep -v "Using a password on the command line interface can be insecure" || true
}

function env {
  echo -n "${!1}"
}

function createdb {
  local mysql_version=$1
  local dbname=$2
  local charset=$3
  local collation=$4

  local host="$(env MYSQL${mysql_version}_HOST)"
  local port="$(env MYSQL${mysql_version}_PORT)"
  local user="$(env MYSQL${mysql_version}_USER)"
  local passwd="$(env MYSQL${mysql_version}_PASSWORD)"

  echo "Creating $dbname at mysql$mysql_version..."

  echo "DROP DATABASE IF EXISTS $dbname" \
    | mysql --ssl-mode=DISABLED -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn

  echo "CREATE DATABASE $dbname CHARSET $charset COLLATE $collation" \
    | mysql --ssl-mode=DISABLED -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn

  cat tests/assets/schema.sql \
    | mysql --ssl-mode=DISABLED -h $host -P $port -u $user -p$passwd $dbname 2>&1 \
    | hide_passwd_warn
}

function dropdb {
  local mysql_version=$1
  local dbname=$2

  local host="$(env MYSQL${mysql_version}_HOST)"
  local port="$(env MYSQL${mysql_version}_PORT)"
  local user="$(env MYSQL${mysql_version}_USER)"
  local passwd="$(env MYSQL${mysql_version}_PASSWORD)"

  echo "Droping $dbname at mysql$mysql_version..."

  echo "DROP DATABASE IF EXISTS $dbname" \
    | mysql --ssl-mode=DISABLED -h $host -P $port -u $user -p$passwd 2>&1 \
    | hide_passwd_warn
}

function createdbs {
  createdb 56 db01 utf8 utf8_general_ci
  createdb 56 db02 utf8 utf8_general_ci
  createdb 56 db03 latin1 latin1_swedish_ci

  dropdb 57 replica_db03
  dropdb 57 replica_db01
  dropdb 57 replica
  dropdb 57 db03

  # createdb 57 db01 utf8 utf8_general_ci
  # createdb 57 db02 utf8 utf8_general_ci
  # createdb 57 db03 latin1 latin1_swedish_ci
  #
  # createdb 80 db01 utf8 utf8_general_ci
  # createdb 80 db02 utf8 utf8_general_ci
  # createdb 80 db03 latin1 latin1_swedish_ci
}

function run_containers {
  docker-compose up -d || true
}

function main {
  load_env
  # run_containers
  createdbs
}

main
