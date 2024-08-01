---
title: Improve focus handling for modal and offcanvas content
issue: NEXT-33696
---
# Storefront
* Added new `window.focusHandler` helper class to save and resume focus states.
* Added focus handling to `ajax-modal.plugin.js` to resume focus after the modal is closed.
* Added focus handling to `offcanvas.plugin.js` to resume focus after the offcanvas is closed.
* Added focus handling to `address-editor.plugin.js` to resume focus after the editor is closed.
* Changed the action elements in `address-editor-modal.html.twig` from `div` to `button` for better keyboard navigation.
___
# Upgrade Information
## Storefront focus handler helper
To improve accessibility while navigating via keyboard you can use the `window.focusHandler` to save and resume focus states. This is helpful if an element opens new content in a modal or offcanvas menu. While the modal is open the users should navigate through the content of the modal. If the modal closes the focus state should resume to the element which opened the modal, so users can continue at the position where they left. The default Shopware plugins `ajax-modal`, `offcanvas` and `address-editor` will use this behaviour by default. If you want to implement this behaviour in your own plugin, you can use the `saveFocusState` and `resumeFocusState` methods. Have a look at the class `Resources/app/storefront/src/helper/focus-handler.helper.js` to see additional options.
