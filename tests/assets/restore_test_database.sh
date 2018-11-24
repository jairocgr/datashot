#!/usr/bin/env bash

cd "${0%/*}" # change current dir to assets folder

mysql < datashot.sql
