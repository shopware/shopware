---
title: Add Email Idn handling
issue: NEXT-34379
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Added `Shopware\Core\Checkout\Customer\Service::EmailIdnService` to handle decode and encode idn email for customer in the storefront
* Changed `ChangeEmailRoute::change`, `LoginRoute::login`, `RegisterRoute::register`, `SendPasswordRecoveryMailRoute::sendRecoveryMail` to use the formatted email from EmailIdnService
___
# Administration
* Added `src/Administration/Resources/app/administration/src/app/filter/decode-idn-email.filter.ts` to handle decode idn email in the admin
