---
title: Change media import error handling
issue: NEXT-7954
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Added `InvalidMediaUrlException.php` and `MediaDownloadException.php`
* Changed the behaviour for invalid URLs or failed downloads during media import to count them as failed records with an error message / exception in `MediaSerializer.php`
* Added serializers which add a `_error` key will now trigger the error handling in `ImportExport.php`
