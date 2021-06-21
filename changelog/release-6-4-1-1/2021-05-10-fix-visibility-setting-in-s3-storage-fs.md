---
title: fix visibility setting in s3 storage fs
issue: NEXT-14744
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed setting of visibility from fallback config to nested `options` parameter as per documentation
* Added `s3:set-visibility` CLI command for retroactively setting visibility of already stored objects
