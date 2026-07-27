---
title: Keep the customer in the checkout flow when login fails during checkout
author: Chuc Le Manh
author_email: c.lemanh@shopware.com
---
# Storefront
* Changed `Shopware\Storefront\Controller\AuthController::login()` so a failed login started from the checkout register page (`/checkout/register`) is forwarded back to `frontend.checkout.register.page` instead of the standalone account login page. The origin is detected from the submitted `redirectTo`, so customers who enter wrong credentials during checkout stay within the checkout flow.
* Changed `Shopware\Storefront\Controller\RegisterController::checkoutRegisterPage()` to pass the `loginError`, `errorSnippet` and `waitTime` parameters to the template, so the login error is rendered inline on the checkout register page.
* Changed `Resources/views/storefront/page/checkout/address/index.html.twig` to expand the collapsed login panel (`#loginCollapse`) automatically when `loginError` is set, so the wrong-credentials message and the login form are visible on load instead of hidden inside the collapsed panel.
