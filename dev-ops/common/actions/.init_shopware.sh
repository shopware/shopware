#!/usr/bin/env bash
#DESCRIPTION: initialization of shopware

bin/console translation:import --with-plugins

bin/console denormalize:build:category:path

bin/console denormalize:index:products

bin/console seo:url:generate