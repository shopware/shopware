---
title: Add Cookie Configuration for Youtube
issue: NEXT-26658
---
# Storefront
* Changed `src/Storefront/Framework/Cookie/CookieProvider.php` to add a new comfort feature cookie calling youtube-video
* Changed the following methods in `cms-gdpr-video-element.plugin.js` to replace the element with video if needed
    * `init`
    * `onReplaceElementWithVideo`
* Added `_replaceElementWithVideo` method in `cms-gdpr-video-element.plugin.js` to replace the element with video
* Added new `document.$emitter` event to `_handleSubmit` in `cookie-configuration.plugin.js` to dispatch after the off-canvas close
