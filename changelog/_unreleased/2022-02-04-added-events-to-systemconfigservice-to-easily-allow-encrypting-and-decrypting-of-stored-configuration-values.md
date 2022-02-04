---
title: Added events to SystemConfigService to easily allow encrypting and decrypting of stored configuration values in combination with decorating the SystemConfigLoader.
issue: NEXT-19942
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Added `Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent` and dispatch it before a system config value is stored.
* Added `\Shopware\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent` and dispatch it after the domain configuration is loaded.
