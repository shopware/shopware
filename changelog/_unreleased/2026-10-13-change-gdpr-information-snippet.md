---
title: Change GDPR information snippet
issue: 21318
---
# Storefront
* Changed `component/privacy-notice.html.twig` to show the new snippet `general.privacyNoticeInformation` instead of the consent text when `core.loginRegistration.requireDataProtectionCheckbox` is disabled.
* Added the snippet `checkout.registerSubmit` for the submit buttons of the checkout registration and `page/account/guest-auth.html.twig`. `account.registerSubmit` now reads "Register".
* Changed the English snippets `general.privacyNoticeText`, `general.privacyNoticeTextModal` and `general.privacyNoticeTextPage`, which referred to a "continue" button.
