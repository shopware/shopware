---
title: Improved Checkout accessibility
issue: NEXT-38513
---
# Core
* Changed `Shopware\Core\Checkout\Payment\PaymentMethodCollection` to not re-sort selected payment method.
* Changed `Shopware\Core\Checkout\Shipping\ShippingMethodCollection` to not re-sort selected shipping method.
___
# Storefront
* Changed `Resources/views/storefront/component/payment/payment-form.html.twig` to list all payment methods instead of only 5.
* Changed `Resources/views/storefront/component/payment/payment-method.html.twig` to show the payment method description only for selected payment method.
* Changed `Resources/views/storefront/component/shipping/shipping-form.html.twig` to list all shipping methods instead of only 5.
* Changed `Resources/views/storefront/component/shipping/shipping-method.html.twig` to show the shipping method description only for selected shipping method.
* Deprecated `Resources/views/storefront/component/payment/payment-fields.html.twig` to remove collapse logic, payment method list is now in `Resources/views/storefront/component/payment/payment-form.html.twig`
* Deprecated `Resources/views/storefront/component/shipping/shipping-fields.html.twig` to remove collapse logic, payment method list is now in `Resources/views/storefront/component/shipping/shipping-form.html.twig`
* Deprecated blocks for rename:
  * `page_checkout_change_payment_form` to `component_payment_form`
  * `page_checkout_change_payment_form_element` to `component_payment_form_element`
  * `page_checkout_change_payment_form_redirect` to `component_payment_form_redirect`
  * `page_checkout_change_payment_form_fields` to `component_payment_form_list`
  * `page_checkout_change_shipping_form` to `component_shipping_form`
  * `page_checkout_change_shipping_form_element` to `component_shipping_form_element`
  * `page_checkout_change_shipping_form_redirect` to `component_shipping_form_redirect`
  * `page_checkout_change_shipping_form_fields` to `component_shipping_form_list`
* Deprecated blocks for removal:
  * `component_shipping_methods`
  * `component_shipping_method`
  * `component_shipping_method_collapse`
  * `component_shipping_method_collapse_trigger`
  * `component_shipping_method_collapse_trigger_label`
  * `component_shipping_method_collapse_trigger_icon`
  * `component_payment_methods`
  * `component_payment_method`
  * `component_payment_method_collapse`
  * `component_payment_method_collapse_trigger`
  * `component_payment_method_collapse_trigger_label`
  * `component_payment_method_collapse_trigger_icon`
* Deprecated plugin `CollapseCheckoutConfirmMethodsPlugin`
* Deprecated CSS classes `confirm-checkout-collapse-trigger` and `icon-confirm-checkout-chevron`
___
# Next Major Version Changes
## Payment & shipping method display in Shopware 6.7
The payment and shipping method selection in the checkout in the Storefront has been improved for accessibility.
There are now all methods listed instead of only 5, which makes the `CollapseCheckoutConfirmMethodsPlugin` unnecessary and will be removed.
Also, the payment and shipping method descriptions are now only shown for the selected method.
To clean up the collapse logic, the following templates will be removed:
* `Resources/views/storefront/component/payment/payment-fields.html.twig`, integrated into `Resources/views/storefront/component/payment/payment-form.html.twig`
* `Resources/views/storefront/component/shipping/shipping-fields.html.twig`, integrated into `Resources/views/storefront/component/shipping/shipping-form.html.twig`
