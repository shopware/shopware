---
title: Fix issue when the same placeholder for the CSRF token is used more than once in a template
issue: NEXT-20694
author: Alexander Schneider
author_email: alexanderschneider85@gmail.com
author_github: GM-Alex
---
# Core
* Changed `Shopware\Storefront\Framework\Csrf\CsrfPlaceholderHandler` to handle multiple usages of the same CSRF placeholder in one template correctly
