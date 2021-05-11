---
title: Fixed field definition for order line items description field
author: Peter Roj
author_email: roj@juicy-arts.de 
author_github: @JuicyLung91
---
# Core
* Added and replaced LongTextField to OrderLineItems to match with the migration and prevent an error for line item description that is longer than 255 characters.
    * File Changed: src/Core/Checkout/Order/Aggregate/OrderLineItem/OrderLineItemDefinition.php
    * The field `description` is now a `LongTextField` and not a `StringField` which is limited to 255 chars
