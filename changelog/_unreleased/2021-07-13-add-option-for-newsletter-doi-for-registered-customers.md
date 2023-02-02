---
title: Add option for newsletter DOI for registered customers
issue: NEXT-14001
flag: FEATURE_NEXT_14001
 
---
# Core
* Deprecated property `newsletter` in `Core/Checkout/Customer/CustomerEntity.php`.
* Deprecated field `newsletter` in `Core/Checkout/Customer/CustomerDefinition.php`.
* Deprected column `newsletter` in table `customer`.
* Added `Core/Checkout/Customer/Subscriber/CustomerNewsletterSubscriber.php`
* Added `Core/Checkout/Customer/SalesChannel/AbstractNewsletterRecipientRoute.php`
* Added `Core/Checkout/Customer/SalesChannel/NewsletterRecipientRoute.php`
* Added `Core/Checkout/Customer/SalesChannel/NewsletterRecipientRouteResponse.php`
* Added `Core/Content/Newsletter/SalesChannel/SalesChannelNewsletterRecipientDefinition.php`
* Added constant `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute::OPTION_DIRECT`
* Added constant `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute::OPTION_SUBSCRIBE`
* Added constant `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute::OPTION_UNSUBSCRIBE`
* Added constant `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute::OPTION_CONFIRM_SUBSCRIBE`
* Added system config `core.newsletter.doubleOptInRegistered` in `Core/System/Resources/config/newsletter.xml`
* Added `Core/Migration/V6_4/Migration1625569667NewsletterDoiForRegistered.php` to add key `core.newsletter.doubleOptInRegistered` in `system_config` 
___
# API
* Added new store-api route `/store-api/account/newsletter-recipient`
___
# Storefront
* Deprecated `\Shopware\Storefront\Controller\NewsletterController::$customerRepository`
* Deprecated `\Shopware\Storefront\Controller\NewsletterController::$newsletterSubscribeRoute`
* Deprecated `\Shopware\Storefront\Controller\NewsletterController::$newsletterUnsubscribeRoute`
* Deprecated `\Shopware\Storefront\Controller\NewsletterController::hydrateFromCustomer`
* Deprecated `\Shopware\Storefront\Controller\NewsletterController::setNewsletterFlag`
* Changed `\Shopware\Storefront\Controller\NewsletterController::subscribeCustomer` to move logic to PageLoader
* Changed  `\Shopware\Storefront\Controller\NewsletterController::__construct` by adding `\Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader` as parameter
* Added property `newsletterAccountPagelet` to `Storefront/Page/Account/Overview/AccountOverviewPage.php`
* Added property `newsletterAccountPageletLoader` to `Storefront/Page/Account/Overview/AccountOverviewPageLoader.php`
* Changed `\Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader::load` to add `newsletterAccountPagelet` by seperate Loader.
* Added `Storefront/Pagelet/Newsletter/Account/NewsletterAccountPagelet.php`
* Added `Storefront/Pagelet/Newsletter/Account/NewsletterAccountPageletLoadedEvent.php`
* Added `Storefront/Pagelet/Newsletter/Account/NewsletterAccountPageletLoader.php`
* Changed block `page_account_overview_newsletter_content` in `Storefront/Resources/views/storefront/page/account/index.html.twig` to change passed vars
* Deprecated variable `success` in `Storefront/Resources/views/storefront/page/account/newsletter.html.twig`, this will be replaced by `newsletterAccountPagelet.success`
* Deprecated variable `messages` in `Storefront/Resources/views/storefront/page/account/newsletter.html.twig`, this will be replaced by `newsletterAccountPagelet.messages`
* Deprecated variable `customer` in `Storefront/Resources/views/storefront/page/account/newsletter.html.twig`, this will be replaced by `newsletterAccountPagelet.customer`
* Changed `Storefront/Resources/views/storefront/page/account/newsletter.html.twig` to handle DOI on newsletter subscription
* Added `Storefront/Test/Controller/NewsletterControllerTest.php`
