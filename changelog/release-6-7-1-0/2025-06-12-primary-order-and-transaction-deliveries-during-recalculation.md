---
title: Primary order and transaction deliveries during recalculation
---
# Core
* Changed `Checkout/Cart/Order/OrderConverter.php` to move the primary order delivery to the beginning of the deliveries array in `convertDeliveries`. This supports legacy behaviour where `$deliveries->first()` is used to access the primary delivery.
* Added two new cart extensions: `ORIGINAL_PRIMARY_ORDER_DELIVERY` and `ORIGINAL_PRIMARY_ORDER_TRANSACTION` to store the original primary IDs during recalculation. Note: Changing these values **does not** affect which delivery or transaction is treated as primary.
* Changed `Checkout/Cart/Delivery/DeliveryProcessor::process` to rely on the primary delivery ID.
* Added `Checkout/Cart/Delivery/Struct/DeliveryCollection::getPrimaryDelivery` to return the primary delivery based on the primary delivery ID.
___
# Upgrade Information
## Primary delivery ordering and read-only cart extensions
The `OrderConverter` now explicitly moves the **primary order delivery** to the front of the deliveries list. This ensures legacy compatibility for existing usages of `$deliveries->first()`.
Two new cart extensions are introduced:
- `ORIGINAL_PRIMARY_ORDER_DELIVERY` – returns the originally determined primary order delivery.
- `ORIGINAL_PRIMARY_ORDER_TRANSACTION` – returns the originally determined primary order transaction.

These extensions serve as **informational only**: modifying them does **not** change the actual primary delivery or transaction set in the order.