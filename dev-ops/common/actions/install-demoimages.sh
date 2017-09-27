#!/usr/bin/env bash
#DESCRIPTION: download and migrate demo data images

wget -O ./web/test_images.zip http://releases.s3.shopware.com/test_images.zip

I: unzip -qo ./web/test_images.zip -d ./web/

bin/console media:migrate md5
