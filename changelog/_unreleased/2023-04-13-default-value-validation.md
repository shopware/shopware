---
title: Default value validation
issue: NEXT-25160
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition::getDefaults` behavior, that these values are not validated in case of write protection
