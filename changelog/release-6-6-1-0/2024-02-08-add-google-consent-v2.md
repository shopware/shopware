---
title: Add Google Consent V2
issue: NEXT-32925
---
# Storefront
* Added new cookie group `\Shopware\Storefront\Framework\Cookie\CookieProvider::MARKETING_COOKIES` to the cookie provider
* Added new cookie `google-ads-enabled`
* Changed `views/storefront/component/analytics.html.twig` to always set a default consent with `gtag` for considering Google Consent Mode v2