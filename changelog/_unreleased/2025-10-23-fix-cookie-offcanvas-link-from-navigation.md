---
title: Fix cookie offcanvas link not working when opened from navigation offcanvas
issue: 13127
author: BrocksiNet
author_email: brocksinet@example.com
author_github: @BrocksiNet
---
# Storefront
* Changed `CookieConfiguration` plugin to use event delegation instead of direct event listeners
  * This ensures dynamically loaded links (e.g., from navigation offcanvas) are properly handled
  * Event listener now uses capture phase to intercept clicks before other handlers
* Changed `OffCanvas` plugin to properly dispose of Bootstrap Offcanvas instances
  * This fixes backdrop cleanup issues when replacing one offcanvas with another
___
# Upgrade Information
## Cookie offcanvas links in dynamically loaded content now work correctly
Previously, links to open the cookie offcanvas that were loaded dynamically (e.g., within the navigation offcanvas) 
would not work and would navigate to the URL as a full page instead. This has been fixed by implementing event 
delegation in the `CookieConfiguration` plugin.

### Before
```javascript
_registerEvents() {
    Array.from(document.querySelectorAll(customLinkSelector)).forEach(customLink => {
        customLink.addEventListener(submitEvent, this._handleCustomLink.bind(this));
    });
}
```

### After
```javascript
_registerEvents() {
    document.addEventListener(submitEvent, (event) => {
        const target = event.target;
        const customLink = target.closest(customLinkSelector);
        if (customLink) {
            this._handleCustomLink(event);
            return;
        }
        // ... other handlers
    }, true); // Use capture phase
}
```

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your 
implementation to use event delegation as well.

