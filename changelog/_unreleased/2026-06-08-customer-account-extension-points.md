---
title: Add customer account extension points (login / register / password recovery)
issue: NEXT-00000
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: @OliverSkroblin
---
# Core
* Added `Shopware\Core\Checkout\Customer\Extension\LoginByCredentialsExtension`, published around `AccountService::loginByCredentials`. A subscriber may verify the credentials itself, assign the resulting context token to `$extension->result` and call `stopPropagation()` to short-circuit the core login.
* Added `Shopware\Core\Checkout\Customer\Extension\RegisterCustomerExtension`, published around `RegisterRoute::register`. A subscriber may abort the registration by throwing, or replace it via `$extension->result`.
* Added `Shopware\Core\Checkout\Customer\Extension\SendRecoveryMailExtension`, published around `SendPasswordRecoveryMailRoute::sendRecoveryMail`.
* Added `Shopware\Core\Checkout\Customer\Extension\ResetPasswordExtension`, published around `ResetPasswordRoute::resetPassword`.
* Added `Shopware\Core\Checkout\Customer\Extension\RecoveryIsExpiredExtension`, published around `CustomerRecoveryIsExpiredRoute::load`.
___
# Upgrade Information
## Customer account extension points

The customer login, registration and password-recovery store-api operations are now wrapped with Shopware's `Extension` mechanism (`ExtensionDispatcher::publish`), so they can be extended via subscribers instead of decorating `AccountService` or the routes.

For each `Extension` you can subscribe to:

* `<Extension>::onPre()` — runs before the core operation; assign `$extension->result` and call `stopPropagation()` to replace it, or throw to abort.
* `<Extension>::onPost()` — runs after the operation; inspect or mutate `$extension->result`.
* `<Extension>::onError()` — runs when the operation threw; assign a fallback `$extension->result` to recover.

```php
public static function getSubscribedEvents(): array
{
    return [LoginByCredentialsExtension::onPre() => 'onLogin'];
}

public function onLogin(LoginByCredentialsExtension $extension): void
{
    // $extension->email, $extension->password, $extension->context are public
    $extension->result = $token;     // EntitySearchResult/token/response depending on the extension
    $extension->stopPropagation();   // skip the core implementation
}
```
