---
title: Undundle storage adapters
issue: NEXT-26756
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Removed dependency on storage adapter packages `league/flysystem-async-aws-s3` and `league/flysystem-google-cloud-storage`. These are now optional and can be installed separately.
___
# Upgrade Information
## Removed dependencies to storage adapters
Removed composer packages `league/flysystem-async-aws-s3` and `league/flysystem-google-cloud-storage`. If your installation uses the AWS S3 or Google Cloud storage adapters, you need to install the corresponding packages separately.

Run the following commands to install the packages:
```bash
composer require league/flysystem-async-aws-s3
composer require league/flysystem-google-cloud-storage
```
