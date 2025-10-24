---
title: Fix cookie offcanvas link not working when opened from navigation offcanvas
issue: 13127
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `CookieConfiguration` plugin to use event delegation instead of direct event listeners
  * This ensures dynamically loaded links (e.g., from navigation offcanvas) are properly handled
  * Event listener now uses capture phase to intercept clicks before other handlers
  * Added proper event handler cleanup in `destroy()` method to prevent memory leaks
  * Added guards to prevent duplicate event handler registrations
  * Enhanced click handling to support middle-click and Ctrl/Cmd+click for normal browser behavior
* Changed `OffCanvas` plugin to properly dispose of Bootstrap Offcanvas instances
  * This fixes backdrop cleanup issues when replacing one offcanvas with another
  * Added proper type checking before calling `dispose()` method
  * Clear singleton reference after disposal for proper garbage collection
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
    // Prevent duplicate event handler registration
    if (this._delegatedEventHandler) {
        return;
    }

    // Store the handler reference for cleanup
    this._delegatedEventHandler = (event) => {
        const target = event.target;
        const customLink = target.closest(customLinkSelector);
        if (customLink) {
            this._handleCustomLink(event);
            return;
        }
        // ... other handlers
    };

    document.addEventListener(submitEvent, this._delegatedEventHandler, true);
}

destroy() {
    // Remove delegated event handler
    if (this._delegatedEventHandler) {
        document.removeEventListener(this.options.submitEvent, this._delegatedEventHandler, true);
        this._delegatedEventHandler = null;
    }
    // ... other cleanup
}
```

## Enhanced click handling for better user experience
The plugin now properly handles different click types:
- **Normal left-click**: Opens cookie offcanvas
- **Middle-click**: Opens link in new tab (browser default behavior)
- **Ctrl/Cmd+click**: Opens link in new tab (browser default behavior)
- **Right-click**: Shows context menu (browser default behavior)

## Improved memory management and resource cleanup
- Added proper event handler cleanup in `destroy()` method
- Added guards to prevent duplicate event handler registrations
- Enhanced Bootstrap Offcanvas instance disposal with proper type checking
- Clear singleton references after disposal for proper garbage collection

If you have extended the `CookieConfiguration` plugin and override `_registerEvents()`, you may need to update your 
implementation to use event delegation as well.
