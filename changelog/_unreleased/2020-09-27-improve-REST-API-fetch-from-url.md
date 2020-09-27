---
title: Change check for Content-Type in fetchfile()
issue: (issue)[https://github.com/shopware/platform/issues/1372]
author: Marcel Sotiropoulos
author_email: marcel.sotiropoulos@soti-it.at
---
# API
*  Changed the check for Content-Type in MediaService.php in function fetchFile(...) to  
```strpos($contentType, 'application/json') !== false``` instead of  
```$contentType === 'application/json'```