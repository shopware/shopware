#!/usr/bin/env bash

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ --port=__DB_PORT__ -e "DROP DATABASE IF EXISTS __DB_NAME__"
mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ --port=__DB_PORT__ -e "CREATE DATABASE __DB_NAME__ DEFAULT CHARACTER SET utf8mb4 DEFAULT COLLATE utf8mb4_unicode_ci"

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ --port=__DB_PORT__ __DB_NAME__ < _sql/schema.sql
mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ --port=__DB_PORT__ __DB_NAME__ < _sql/schema_data.sql

mysql -u __DB_USER__ -p__DB_PASSWORD__ -h __DB_HOST__ --port=__DB_PORT__ __DB_NAME__ -e "UPDATE shop SET host='__SW_HOST__', base_url='__SW_BASE_PATH__'"

./_sql/ApplyDeltas.php --migrationpath="./_sql/migrations/" --shoppath="./" --mode=update --dbname=__DB_NAME__ --host=__DB_HOST__ --password=__DB_PASSWORD__ --username=__DB_USER__ --port=__DB_PORT__
