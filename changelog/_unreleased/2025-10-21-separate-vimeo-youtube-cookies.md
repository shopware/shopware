---
title:              Separate Vimeo and YouTube cookie consent
issue:              https://github.com/shopware/shopware/issues/6409
---
# Core
*  Added new method `getVimeoVideoEntry` in `src/Core/Content/Cookie/Service/CookieProvider.php` to register separate Vimeo video cookie
*  Added `vimeo-video` cookie entry to comfort features cookie group in `src/Core/Content/Cookie/Service/CookieProvider.php`
___
# Storefront
*  Added `vimeo-video` cookie entry to comfort features in `src/Storefront/Framework/Cookie/CookieProvider.php`
*  Added `cookieName: 'vimeo-video'` to plugin configuration in `src/Storefront/Resources/views/storefront/element/cms-element-vimeo-video.html.twig`
*  Added translation `cookie.groupComfortFeaturesVimeoVideo` in `src/Storefront/Resources/snippet/storefront.en.json`
*  Added translation `cookie.groupComfortFeaturesVimeoVideo` in `src/Storefront/Resources/snippet/storefront.de.json`
*  Changed `_replaceElementWithVideo` in `src/Storefront/Resources/app/storefront/src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin.js` to check cookie consent before replacing placeholder with video iframe
*  Changed `init` in `src/Storefront/Resources/app/storefront/src/plugin/cms-gdpr-video-element/cms-gdpr-video-element.plugin.js` to subscribe to `COOKIE_CONFIGURATION_UPDATE` event for immediate video loading when cookies are accepted
___
# Upgrade Information

## Vimeo and YouTube Cookie Consent Separation

With this change, Vimeo and YouTube videos now use separate cookie consent entries and load immediately when cookies are accepted, improving user experience and GDPR compliance.

### Behavior Changes

**Before:**
- Accepting "YouTube video" cookie → both YouTube AND Vimeo videos load (incorrect)
- Videos required page reload to display after accepting cookies
- Global event caused all videos to attempt loading regardless of cookie consent

**After:**
- Accepting "YouTube video" cookie → only YouTube videos load
- Accepting "Vimeo video" cookie → only Vimeo videos load
- Videos load immediately when cookies are accepted (no page reload needed)
- Each video type checks its specific cookie before loading

### Technical Improvements

1. **Separate Cookie Entries**: Vimeo videos now use a dedicated `vimeo-video` cookie instead of sharing the `youtube-video` cookie
2. **Cookie Validation**: The plugin now validates that the correct cookie is set before replacing the placeholder with the video iframe
3. **Immediate Loading**: Videos now load immediately when cookies are accepted via "Accept all" button or cookie configuration offcanvas (subscribes to `COOKIE_CONFIGURATION_UPDATE` event)

### Impact on Existing Users

Users who previously accepted the YouTube cookie will need to separately accept the Vimeo cookie to view Vimeo content. The cookie configuration hash will change, which may trigger the cookie banner to reappear for all users, giving them the opportunity to review and update their preferences.

This change ensures proper GDPR compliance and gives users granular control over which video platforms they consent to, while providing a smoother user experience with immediate video loading.

