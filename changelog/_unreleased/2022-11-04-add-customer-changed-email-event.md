---
title: Add customer changed email event
issue: N/A
author: Melvin Achterhuis
author_email: melvin.achterhuis@iodigital.com
---
# Core
* Changed class `BusinessEvents` in `src/Core/Framework/Event/BusinessEvents.php` to add new const.
* Changed function `change` in `src/Core/Checkout/Customer/SalesChannel/ChangeEmailRoute.php` to dispatch event.
* Added `src/Core/Checkout/Customer/Event/CustomerChangedEmailEvent.php` for new event.

# Storefront
* Changed function `saveEmail` in `src/Storefront/Controller/AccountProfileController.php` to dispatch event.
* Changed service `Shopware\Storefront\Controller\AccountProfileController` to add `event_dispatcher`.
