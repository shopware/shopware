---
title: Fixed field definition for order line items description field
issue: NEXT-15292
author: Peter Roj
author_email: roj@juicy-arts.de 
author_github: @JuicyLung91
---
# Core
* Changed OrderLineItem description to LongTextField to match with the migration and prevent an error for line item descriptions that are longer than 255 characters.
