---
title: fix-fatal-error-with-missing-custom-fields
issue: NEXT-13579
author: Tobias Kluth
author_email: t_kluth@gmx.de 
author_github: @tobiaskluth
---
# Core
*  Changed `src/Core/Checkout/Cart/Rule/LineItemCustomFieldRule.php` function `isCustomFieldValid` to evaluate missing custom fields to a false statement instead of throwing fatal error.