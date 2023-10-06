---
title: Disable default cookie notification
issue: NEXT-9096
---
# Core
* Added a migration `Migration1650444800AddDefaultSettingConfigValueForUseCookiesNotification` to add the default config `core.basicInformation.useDefaultCookieConsent`
___
# Storefront
* Added a block `layout_head_javascript_cookie_state` in head tag at `src/Storefront/Resources/views/storefront/layout/meta.html.twig`
* Changed `src/Storefront/Resources/views/storefront/layout/cookie/cookie-permission.html.twig` to set condition `core.basicInformation.useDefaultCookieConsent` to show or hide default cookies notification.
