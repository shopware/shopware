---
title: Fix app signature generation during app install
issue: NEXT-15688
---
# Core
* Changed `\Shopware\Core\Framework\Store\Services\StoreClient::signPayloadWithAppSecret` to send the correct query parameters, thus fixing the app registration.
