---
title: Always return plain object from SystemConfigService
issue: NEXT-16966
---
# Administration
* `SystemConfigService::getValues` will now always return a plain object even if return value from api is empty.
