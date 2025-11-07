---
title: Fix cookie offcanvas link not working when opened from navigation offcanvas
issue: 13127
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `CookieConfiguration` plugin to use event delegation for dynamically loaded links
  * Added proper event handler cleanup in `destroy()` method to prevent memory leaks
  * Added guards to prevent duplicate event handler registrations
  * Enhanced click handling to support middle-click and Ctrl/Cmd+click for normal browser behavior
* Changed `OffCanvas` plugin to properly dispose of Bootstrap Offcanvas instances
  * This fixes backdrop cleanup issues when replacing one offcanvas with another
* Changed `ajax-offcanvas.plugin.js` and removed the `closable` parameter from the `setContent` method, to fix animation issues when closing offcanvas.
___
# Upgrade Information

## Cookie offcanvas links in dynamically loaded content
Links to open the cookie offcanvas that are loaded dynamically (e.g., within the navigation offcanvas) now work correctly. 
The `CookieConfiguration` plugin now uses event delegation instead of direct event listeners.

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your 
implementation to use event delegation as well.
