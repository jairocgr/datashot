#!/usr/bin/env bash

cd "${0%/*}" # change current dir to assets folder

mysql -e "drop database if exists snaped_database"
mysql -e "create database if not exists snaped_database"

gunzip < snapped.snap.gz | mysql snaped_database
