---
title: Fix payment method indexing
issue: NEXT-31876
author: Niklas Wolf
author_email: wolfniklas94@web.de
author_github: @niklaswolf
---
# Core
* Changed `PaymentMethodIndexer`to prevent infinite loop during due to distinguishible name generation
