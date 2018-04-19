#!/usr/bin/env bash

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ -e "DROP DATABASE IF EXISTS __DB_NAME__"
mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ -e "CREATE DATABASE __DB_NAME__ DEFAULT CHARACTER SET utf8 DEFAULT COLLATE utf8_unicode_ci"

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ __DB_NAME__ < _sql/install/latest.sql

./_sql/ApplyDeltas.php --migrationpath="./_sql/migrations/" --shoppath="./" --mode=update --dbname=__DB_NAME__ --host=__DB_HOST__ --password=__DB_PASSWORD__ --username=__DB_USER__

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ __DB_NAME__ < _sql/demo/latest.sql

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ __DB_NAME__ < _sql/fixup.sql
mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ __DB_NAME__ < _sql/destructive.sql
