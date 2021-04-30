---
title: Auto submit payment and shipping checkout form only on needed element change
issue: NEXT-15051
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Storefront
* Added plugin option `changeTriggerSelectors` in `Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js`
* Changed method `_onChange` in `Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` to verify if configured selectors exist and match the change events target
* Changed method `init` in `Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` and throw new Error when option `changeTriggerSelectors` is used but not an array
* Added new twig variable `formAjaxSubmitOptions` in `Resources/views/storefront/component/payment/payment-form.html.twig` to set options for `data-form-auto-submit`
    * Added new inner twig block `page_checkout_change_payment_form_element` to allow overwriting `formAjaxSubmitOptions`
* Added new twig variable `formAjaxSubmitOptions` in `Resources/views/storefront/component/shipping/shipping-form.html.twig` to set options for `data-form-auto-submit`
    * Added new inner twig block `page_checkout_change_shipping_form_element` to allow overwriting `formAjaxSubmitOptions`
