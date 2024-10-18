---
title: Added WriteBatchInterface
issue: NEXT-0000
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---

# Core

* Added WriteBatchInterface to support custom batch writes in flysystem adapters besides custom s3 solution
* Added dedicated S3WriteBatchAdapter to support batch writes for S3
* Changed WriteBatch to check for implemented Interface and use method copyBatch instead of copy
* Changed classes CopyBatch to WriteBatch and added deprecation notice 

