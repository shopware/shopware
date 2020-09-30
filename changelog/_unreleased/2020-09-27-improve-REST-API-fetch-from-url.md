---
title: Change content type check in `\Shopware\Core\Content\Media\MediaService::fetchFile()`
issue: (issue)[https://github.com/shopware/platform/issues/1372]
author: Marcel Sotiropoulos
author_email: marcel.sotiropoulos@soti-it.at
---
# API
*  Changed the content type check in `\Shopware\Core\Content\Media\MediaService::fetchFile()` to allow additional information
```strpos($contentType, 'application/json') !== false``` instead of  
```$contentType === 'application/json'```
