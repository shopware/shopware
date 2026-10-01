---
title: Separate Vimeo and YouTube cookie consent
issue: 6409
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Added `vimeo-video` cookie entry to comfort features in `Shopware\Storefront\Framework\Cookie\CookieProvider` and in TWIG template `element/cms-element-vimeo-video.html.twig`
* Added translation snippets `cookie.groupComfortFeaturesVimeoVideo` for de and en locales
* Changed `_replaceElementWithVideo` in `cms-gdpr-video-element.plugin.js` to check cookie consent before replacing placeholder with video iframe, and to skip elements that were already replaced
* Changed `init` in `cms-gdpr-video-element.plugin.js` to subscribe to `COOKIE_CONFIGURATION_UPDATE` instead of `COOKIE_CONFIGURATION_CLOSE_OFF_CANVAS`, so videos load immediately for every consent path
___
# Upgrade Information
## Vimeo and YouTube cookie consent separation
Vimeo videos now require their own `vimeo-video` cookie instead of sharing the `youtube-video` cookie, and load immediately when consent is given.
Shoppers who previously consented to the YouTube cookie must consent to the Vimeo cookie once before Vimeo videos are displayed again.

If you override the TWIG block `element_vimeo_video_inner` in your theme or plugin, add `cookieName: 'vimeo-video'` to your `pluginConfiguration`, otherwise the element keeps using the shared `youtube-video` cookie.

`CmsGdprVideoElement._replaceElementWithVideo()` now only replaces the placeholder when the cookie named in `options.cookieName` is set. Consent integrations that publish the `CmsGdprVideoElement_replaceElementWithVideo` event must set that cookie first, and should add `vimeo-video` to their cookie mapping.
