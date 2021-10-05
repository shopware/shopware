---
title: Implement rate limiter
issue: NEXT-13795
author_github: @Dominik28111
---
# Core
* Added exception class `Shopware\Core\Checkout\Customer\Exception\CustomerAuthThrottledException`.
* Added exception class `Shopware\Core\Framework\Api\Controller\Exception\AuthThrottledException`.
* Added compiler pass class `Shopware\Core\Framework\DependencyInjection\CompilerPass\RateLimiterCompilerPass`.
* Added exception class `Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException`.
* Added rate limit policy `Shopware\Core\Framework\RateLimiter\Policy\TimeBackoff`.
* Added rate limiter `Shopware\Core\Framework\RateLimiter\Policy\TimeBackoffLimiter`.
* Added class `Shopware\Core\Framework\RateLimiter\RateLimiterFactory` to extend the factory policies provided by Symfony.
* Added class `Shopware\Core\Framework\RateLimiter\NoLimitRateLimiterFactory` to override rate limit with NoLimiter.
* Added service `Shopware\Core\Framework\RateLimiter\RateLimiter`.
* Added `Shopware\Core\Framework\RateLimiter\RateLimiterFactory` to add possibility to add own limiters.
* Changed method `Shopware\Core\Framework\Framework::build()` to add rate limit to the DI.
* Added method `Shopware\Core\System\User\Recovery\UserRecoveryService::getUserByHash()` to receive user entity by recovery hash.
___
# API
* Changed method `Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute::login()` to implement rate limit.
* Changed method `Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute::resetPassword()` to implement rate limit.
* Changed method `Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute::sendRecoveryMail()` to implement rate limit.
* Changed method `Shopware\Core\Checkout\Order\SalesChannel\OrderRoute::load()` to implement rate limit for guest login.
* Changed method `Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute::load()` to implement rate limit.
* Changed method `Shopware\Core\Framework\Api\Controller\AuthController::token()` to implement rate limit.
* Changed method `Shopware\Core\System\User\Api\UserRecoveryController::createUserRecovery()` to implement rate limit.
___
# Administration
* Added data prop `loginAlertMessage` in `app/administration/src/module/sw-login/view/sw-login-login/index.js`.
* Added computed prop `showLoginAlert` in `app/administration/src/module/sw-login/view/sw-login-login/index.js`.
* Changed method `createNotificationFromResponse` in `module/sw-login/view/sw-login-login/index.js` to display rate limit message.
* Added block `{% block sw_login_login_alert %}` in `module/sw-login/view/sw-login-login/sw-login-login.html.twig` to display login alert.
* Changed method `sendRecoveryMail` in `module/sw-login/view/sw-login-recovery/index.js` to handle error message for rate limit.
* Changed method `displayRecoveryInfo` in `module/sw-login/view/sw-login-recovery/index.js` to progress response for rate limit and forward with the wait time.
* Added computed prop `rateLimitTime` in `module/sw-login/view/sw-login-recovery-info/index.js`.
* Changed block `{% block sw_login_recovery_info_info %}` in `module/sw-login/view/sw-login-recovery-info/sw-login-recovery-info.html.twig` to display rate limit message if rateLimitTime is set.
___
# Storefront
* Changed mhetod `Shopware\Storefront\Controller\AccountOrderController::orderSingleOverview()` to handle rate limit exception and redirect with `waitTime` parameter.
* Changed method `Shopware\Storefront\Controller\AuthController::loginPage()` to pass parameter `waitTime` to twig template.
* Changed method `Shopware\Storefront\Controller\AuthController::guestLoginPage()` to add rate limit message to flashbag.
* Changed method `Shopware\Storefront\Controller\AuthController::login()` to handle rate limit exception and forward with `waitTime` parameter.
* Changed method `Shopware\Storefront\Controller\AuthController::generateAccountRecovery()` to handle rate limit exception and add rate limit message to flashbag.
* Changed method `Shopware\Storefront\Controller\FormController::sendContactForm()` to handle rate limit exception and add an alert to response.
* Changed method `_handleResponse()` in `app/storefront/src/plugin/forms/form-cms-handler.plugin.js` to show alerts of type info.
* Changed `{% block component_account_login_form_error %}` in `views/storefront/component/account/login.html.twig` to display info alert with rate limit message.
___
# Upgrade Information

## Rate Limiter

With 6.4.6.0 we have implemented a rate limit by default to reduce the risk of bruteforce for the following routes:
- `/store-api/account/login`
- `/store-api/account/recovery-password`
- `/store-api/order`
- `/store-api/contact-form`
- `/api/oauth/token`
- `/api/_action/user/user-recovery`

### Rate Limiter configuration

The confiuration for the rate limit can be found in the `shopware.yaml` under the map `shopware.api.rate_limiter`.
More information about the configuration can be found at the [developer documentation](https://developer.shopware.com/docs/guides/hosting/infrastructure/rate-limiter).
Below you can find an example configuration.

```yaml
shopware:
  api:
    rate_limiter:
      example_route:
        enabled: true
        policy: 'time_backoff'
        reset: '24 hours'
        limits:
          - limit: 10
            interval: '10 seconds'
          - limit: 15
            interval: '30 seconds'
          - limit: 20
            interval: '60 seconds'
```

If you plan to create your own rate limits, head over to our [developer documentation](https://developer.shopware.com/docs/guides/plugins/plugins/framework/rate-limiter/add-rate-limiter-to-api-route).
