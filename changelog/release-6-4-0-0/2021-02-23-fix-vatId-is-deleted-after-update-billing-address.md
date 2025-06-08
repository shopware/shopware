title: Fix the VAT number is deleted after update billing address
issue: NEXT-13685
---
# Core
* Removed support parameter `vatId`, use array `vatIds` instead for routes:
    * `Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute:upsert`
    * `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute:register`

