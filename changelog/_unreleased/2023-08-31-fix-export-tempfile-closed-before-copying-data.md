---
title: Fix export temporary file closed before copying data
issue: NEXT-30327
author: Cuong Huynh
author_email: cuong.huynh@pluszwei.io
author_github: cuonghuynh
---
# Core
* Changed the export `\Shopware\Core\Content\ImportExport\Processing\Writer\AbstractFileWriter::flush` to work with Google Bucket adapter
