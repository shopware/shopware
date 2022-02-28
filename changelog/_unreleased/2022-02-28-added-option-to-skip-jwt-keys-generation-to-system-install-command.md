---
title: Added option to skip JWT keys generation to system:install command.
issue: https://github.com/shopware/platform/issues/2358
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopware\Core\Maintenance\System\Command\SystemInstallCommand` to add the option `skip-jwt-keys-generation` that ensures that no JWT keys are generated, if it is executed.
