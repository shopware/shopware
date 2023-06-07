---
title: Use value of BeforeSystemConfigChangedEvent in the SystemConfigService set value.
issue: #3101
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopware\Core\System\SystemConfig\SystemConfigService` to use the value provided by the subscribers of the BeforeSystemConfigChangedEvent to set the config value.
