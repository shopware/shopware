---
title: Change default value for active field in shipping_method table
issue: NEXT-21286
author: Dennis Garding
author_email: d.garding@shopware.com
---

# Core
* Changed the default value of the active field in the shipping_method table from 1 to 0 with version 6.5.0.0. 
___
# Next Major Version Changes
## Create new shipping method
When you create a new shipping method, the default value for the active flag is false, i.e. the method is inactive after saving. 
Please provide the active value if you create shipping methods over the API.
