---
title: Add bound sales channel id column for customer
issue: NEXT-10973
---
# Core
*  Added a nullable `bound_sales_channel_id` foreign key into `customer` table.
*  Added `boundSalesChannel` ManyToOne association to `Shopware\Core\Checkout\Customer\CustomerDefinition`.
*  Added `boundCustomers` OneToMany association to `Shopware\Core\System\SalesChannel\SalesChannelDefinition`.
