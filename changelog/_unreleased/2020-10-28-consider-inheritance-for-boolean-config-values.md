---
title: Consider inheritance for boolean config values
issue: NEXT-9639
author: Philip Gatzka
author_email: p.gatzka@shopware.com 
author_github: @philipgatzka
---
# Core
* Changed `\Shopware\Core\System\SystemConfig\SystemConfigService::getDomain` method so it does not consider the boolean
  value `false` empty anymore.
