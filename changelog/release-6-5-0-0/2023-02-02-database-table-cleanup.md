---
title: Database table cleanup
issue: NEXT-24931
---

# Core

* Removed following unused tables:
    * `dead_message`
    * `message_queue_stats`
    * `mail_template_sales_channel`
    * `sales_channel_rule`
* Removed following unused columns
  * `customer_address.vat_id`
  * `customer_address.newsletter`
  * `customer_address.whitelist_ids`
  * `customer_address.blacklist_ids`
* Removed following unused triggers
  * `customer_address_vat_id_insert`
  * `customer_address_vat_id_update`
  * `order_cash_rounding_insert`
* Changed `itemRounding` and `totalRounding` in the OrderDefinition to required

___

# Upgrade Information

## Removed unused entity fields

Following entity properties/methods has been removed:

- `product.blacklistIds`
- `product.whitelistIds`
- `seo_url.isValid`
