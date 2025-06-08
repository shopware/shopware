---
title: Ensure export temporary file is open before copying data
issue: NEXT-30327
author: Cuong Huynh
author_email: cuong.huynh@pluszwei.io
author_github: cuonghuynh
---
# Core
* Changed `\Shopware\Core\Content\ImportExport\Processing\Writer\AbstractFileWriter::flush` method so the export works with Google bucket adapter
