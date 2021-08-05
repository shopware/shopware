---
title: Make mail links configurable
issue: NEXT-15252
---
# Core
* Added following events:
   * `src/Core/Checkout/Customer/Event/CustomerConfirmRegisterUrlEvent.php`
   * `src/Core/Checkout/Customer/Event/PasswordRecoveryUrlEvent.php`
   * `src/Core/Content/Newsletter/Event/NewsletterSubscribeUrlEvent.php`
* Added event calls to `\Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute::sendRecoveryMail` to enrich recovery mail url.
* Added event calls to `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute::subscribe` to enrich subscribe url.
* Added event calls to `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::getDoubleOptInEvent` to enrich double opt-in url.
* Added new migration `src/Core/Migration/V6_4/Migration1624884801MakeMailLinksConfigurable.php` to add mail url templates into system_config
___
# Administration
* added module `/src/Administration/Resources/app/administration/src/module/sw-settings-newsletter` to settings admnistration
