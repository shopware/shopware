---
title: Fixed Double Opt In Registration redirect to account page even in order process
issue: NEXT-12394
---
# Core
* Added `\Shopware\Core\Migration\Traits\MailSubjectUpdate` class which is a data transfer object for updating a mail's subject
* Added `updateMailSubject`, `updateEnMailSubject`, `updateDeMailSubject` in `\Shopware\Core\Migration\Traits\UpdateMailTrait` to support update a mail's subject in DE and EN languages
* Changed `\Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::getDoubleOptInEvent` to append the `redirectTo` from request parameters into `CustomerDoubleOptInRegistrationEvent`'s confirm url
* Changed text `registration` to `sign-up` of subject, plain text and html text in the registration mail english templates
* Added `translated` getter in the default email template's translatable variables for Registration email template. 
___
# Storefront
* Changed `\Shopware\Storefront\Controller\RegisterController::confirmRegistration` to redirect the page to `redirectTo` after the account confirmation process finished if the `redirectTo` is set in the request's query
