---
title: Fix user recovery request on different default language
issue: NEXT-29959
---
# Core
* Changed `\Shopware\Core\System\User\Recovery\UserRecoveryService::generateUserRecovery` to get the language of an existing sales channel before creating sales channel context
* Added a new domain exception in `\Shopware\Core\System\User\UserException`