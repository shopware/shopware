---
title: To get the [request object] in the [cart hook] and to be able to call the cart hook [with parameters]
issue: [request object] not available in [cart hook]
author: Codixio (Matthias Jakisch)
author_email: support@codixio.com
author_github: codixio
---
# Core
* Changed `src/Core/Checkout/Cart/Hook/CartHook.php` added RequestFacadeFactory::class to getServiceIds function.