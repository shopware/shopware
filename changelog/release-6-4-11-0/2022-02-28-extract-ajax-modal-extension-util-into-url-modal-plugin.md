---
title: Replace AjaxModalExtensionUtil with AjaxModalPlugin
issue: NEXT-12535
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Added new plugin class `src/Storefront/Resources/app/storefront/src/plugin/ajax-modal/ajax-modal.plugin.js`
* Deprecated `AjaxModalExtension`
* Removed private property `ajaxModalExtension` from `src/Storefront/Resources/app/storefront/src/plugin/cookie/cookie-configuration.plugin.js`
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
In the future you can use the new storefront plugin `AjaxModalPlugin` which has more configuration and entrypoints for developers to react to or adjust behaviour.
The plugin is registered to the same selector to ensure non-breaking upgrading by default.
