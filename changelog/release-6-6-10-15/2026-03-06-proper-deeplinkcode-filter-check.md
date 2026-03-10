---
title: Proper check for the deepLinkCode filter type
---
# Core
* Changed the logic in `\Shopware\Core\Checkout\Order\SalesChannel\OrderRoute::load` to ensure the `deepLinkCode` filter is an instance of `\Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter`.
