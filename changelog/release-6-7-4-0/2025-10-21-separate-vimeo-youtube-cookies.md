---
title: Separate Vimeo and YouTube cookie consent
issue: https://github.com/shopware/shopware/issues/6409
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Core
* Added new method `getVimeoVideoEntry` in `src/Core/Content/Cookie/Service/CookieProvider.php` to register separate Vimeo video cookie

___

# Storefront
* Added `vimeo-video` cookie entry to comfort features in `CookieProvider.php` and in TWIG template `element/cms-element-vimeo-video.html.twig`
* Added translation snippets `cookie.groupComfortFeaturesVimeoVideo` for de and en locales
* Changed `_replaceElementWithVideo` in `cms-gdpr-video-element.plugin.js` to check cookie consent before replacing placeholder with video iframe
* Changed `init` in `cms-gdpr-video-element.plugin.js` to subscribe to `COOKIE_CONFIGURATION_UPDATE` event for immediate video loading when cookies are accepted

___

# Upgrade Information

## Vimeo and YouTube Cookie Consent Separation

With this change, Vimeo and YouTube videos now use separate cookie consent entries and load immediately when cookies are accepted, improving user experience and GDPR compliance.
