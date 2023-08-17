---
title: To get the [request object] in the [cart hook] and to be able to call the cart hook [with parameters]
issue: [request object] not available in [cart hook]
author: Codixio (Matthias Jakisch)
author_email: support@codixio.com
author_github: codixio
---
# Core
* Changed `src/Core/Checkout/Cart/Hook/CartHook.php` added RequestFacadeFactory::class to getServiceIds function.
* Changed `src/Core/Framework/Routing/Facade/RequestFacadeFactory.php` commented out \assert($request !== null);
* Changed `src/Core/Framework/Routing/Facade/RequestFacade.php` changed constructor `public function __construct(private readonly ?Request $request)` to allow Request to be null, also changed every method to allow Request to be null, instead of throwing an exeption in that case (more compatible/non-breaking for everyone using the request facade before )
