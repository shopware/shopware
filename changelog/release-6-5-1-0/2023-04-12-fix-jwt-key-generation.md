---
title: Fix JWT key generation
issue: NEXT-26164
---
# Core
* Changed `\Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator` to always generate a key 2048 bits long, thus fixing issues when openssl was configured with a different default key length.
* Added helper method `\Shopware\Core\Checkout\Cart\Event\CartBeforeSerializationEvent::addCustomFieldToAllowList`
