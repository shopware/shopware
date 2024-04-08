---
title: Fix opening ajax modal within ajax modal
issue: NEXT-23962
---
# Storefront
* Added new optional attribute `data-prev-url` to `AjaxModalPlugin` to show an optional back-button when opening a new ajax modal within an already opened ajax modal.
* Added new twig block `component_pseudo_modal_back_btn` to `views/storefront/component/pseudo-modal.html.twig` to allow customizing the back-button.
* Added new optional variable `prevUrl` to include template `views/storefront/element/cms-element-form/form-components/cms-element-form-privacy.html.twig`.
* Changed `PseudoModalUtil.open()` to clean up existing modals first before opening a new modal. This allows toggling between ajax modals triggered by `<a data-ajax-modal="true">`.
* Added `data-form-preserver` to contact form (`views/storefront/element/cms-element-form/form-types/contact-form.html.twig`) to keep the filled fields when reading "Data protection information".
