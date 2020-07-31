---
title: Replace AjaxModalExtensionUtil with UrlModalPlugin
issue: NEXT-12535
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
*  Added new plugin class `src/Storefront/Resources/app/storefront/src/plugin/url-modal/url-modal.plugin.js`
*  Deprecated `AjaxModalExtension`
___
# Upgrade Information

## Modal Refactoring

Previously you had to use the following snippet:
```js
import AjaxModalExtension from 'src/utility/modal-extension/ajax-modal-extension.util';
new AjaxModalExtension(false);
```
to activate modals on elements that match the selector `[data-toggle="modal"][data-url]`.
This is error-prone when used multiple times throughout a single page lifetime as it will open up modals for every execution of this rigid helper.
In the future you can use the new storefront plugin `UrlModalPlugin` which has more configuration and entrypoints for developers to react to or adjust behaviour.
The plugin is registered to the same selector to ensure non-breaking upgrading by default.
