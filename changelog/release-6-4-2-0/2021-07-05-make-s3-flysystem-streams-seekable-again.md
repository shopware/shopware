---
title: Make s3 flysystem streams seekable
issue: NEXT-16050
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory::create()` to disable streamed reads in the S3Adapter, thus allowing seeking on streams from the S3Adapter again.
