---
title:              Extend business events
issue:              NEXT-10702
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Added `event_action.active` field to enable or disable business event actions
* Added `event_action.rules` association
* Added `event_action.salesChannels` association
* Added `event_action_rule` entity to support rule whitelist for business events
* Added `event_action_sales_channel` entity to support sales channel whitelist for business events
* Added `mail_template_id` support for `\Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber::sendMail`
* Added `\Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber::SKIP_MAILS` which allows to disable mails 
* Added `src/Core/Framework/Event/SalesChannelAware.php` interface, which defines if a sales channel id is available inside an object
* Added `src/Core/Framework/Event/SalesChannelAware.php` interface to events:
    * `src/Core/Checkout/Cart/Event/CheckoutOrderPlacedEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerAccountRecoverRequestEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerBeforeLoginEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerDoubleOptInRegistrationEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerChangedPaymentMethodEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerGroupRegistrationAccepted.php`
    * `src/Core/Checkout/Customer/Event/CustomerGroupRegistrationDeclined.php`
    * `src/Core/Checkout/Customer/Event/CustomerLoginEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerLogoutEvent.php`
    * `src/Core/Checkout/Customer/Event/CustomerRegisterEvent.php`
    * `src/Core/Checkout/Customer/Event/DoubleOptInGuestOrderEvent.php`
    * `src/Core/Checkout/Order/Event/OrderStateMachineStateChangeEvent.php`
    * `src/Core/Content/ContactForm/Event/ContactFormEvent.php`
    * `src/Core/Content/Newsletter/Event/NewsletterConfirmEvent.php`
    * `src/Core/Content/Newsletter/Event/NewsletterRegisterEvent.php`
    * `src/Core/Content/Newsletter/Event/NewsletterUpdateEvent.php`
* Added short hand for and, or and xor multi filters:
    * `src/Core/Framework/DataAbstractionLayer/Search/Filter/AndFilter.php`
    * `src/Core/Framework/DataAbstractionLayer/Search/Filter/OrFilter.php`
    * `src/Core/Framework/DataAbstractionLayer/Search/Filter/XOrFilter.php`
* Deprecated `\Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelEntity`, will be removed
* Deprecated `\Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition`, will be removed
* Deprecated `\Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelCollection`, will be removed
* Deprecated `\Shopware\Core\Content\MailTemplate\MailTemplateEntity::$salesChannels`, will be removed
* Deprecated `\Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent::$salesChannelId`, will be removed
