---
title: Make custom fields available for all entities in administration
issue: NEXT-14368
---
# Core
* Added `custom_fields` field to these tables: `promotion_translation`, `product_review`, `event_action`, `salutation_translation`, `document_base_config`.
* Added a new property `customFields` and its getter, setter in these entities: `promotion_translation`, `product_review`, `event_action`, `salutation_translation`, `document_base_config`.
* Added `EntityCustomFieldsTrait` in `Shopware\Core\Framework\DataAbstractionLayer`.
___
# Administration
* Changed `entityNameStore` variable in `src/app/service/custom-field.service.js` to add more available entities for assigning custom fields set.
* Added `customFieldSets` variable in `src/module/sw-event-action/page/sw-event-action-detail/index.js` to get custom field sets.
* Added `sw_event_action_detail_custom_field_sets` block in `src/module/sw-event-action/page/sw-event-action-detail/sw-event-action-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-promotion-v2/view/sw-promotion-v2-detail-base/index.js` to get custom field sets.
* Added `sw_promotion_detail_custom_field_sets` block in `src/module/sw-promotion-v2/view/sw-promotion-v2-detail-base/sw-promotion-v2-detail-base.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-product-stream/page/sw-product-stream-detail/index.js` to get custom field sets.
* Added `sw_prouct_stream_detail_custom_field_sets` block in `src/module/sw-product-stream/page/sw-product-stream-detail/sw-product-stream-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-property/page/sw-property-detail/index.js` to get custom field sets.
* Added `sw_property_detail_custom_field_sets` block in `src/module/sw-property/page/sw-property-detail/sw-property-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-review/page/sw-review-detail/index.js` to get custom field sets.
* Added `sw_review_detail_custom_field_sets` block in `src/module/sw-review/page/sw-review-detail/sw-review-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-country/page/sw-settings-country-detail/index.js` to get custom field sets.
* Added `sw_settings_country_detail_custom_field_sets` block in `src/module/sw-settings-country/page/sw-settings-country-detail/sw-settings-country-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-currency/page/sw-settings-currency-detail/index.js` to get custom field sets.
* Added `sw_settings_currency_detail_custom_field_sets` block in `src/module/sw-settings-currency/page/sw-settings-currency-detail/sw-settings-currency-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/index.js` to get custom field sets.
* Added `sw_settings_customer_group_detail_custom_field_sets` block in `src/module/sw-settings-customer-group/page/sw-settings-customer-group-detail/sw-settings-customer-group-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail/index.js` to get custom field sets.
* Added `sw_settings_delivery_time_detail_custom_field_sets` block in `src/module/sw-settings-delivery-times/page/sw-settings-delivery-time-detail/sw-settings-delivery-time-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-document/page/sw-settings-document-detail/index.js` to get custom field sets.
* Added `sw_settings_document_detail_custom_field_sets` block in `src/module/sw-settings-document/page/sw-settings-document-detail/sw-settings-document-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-language/page/sw-settings-language-detail/index.js` to get custom field sets.
* Added `sw_settings_language_detail_custom_field_sets` block in `src/module/sw-settings-language/page/sw-settings-language-detail/sw-settings-language-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-number-range/page/sw-settings-number-range-detail/index.js` to get custom field sets.
* Added `sw_settings_number_range_detail_custom_field_sets` block in `src/module/sw-settings-number-range/page/sw-settings-number-range-detail/sw-settings-number-range-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-payment/page/sw-settings-payment-detail/index.js` to get custom field sets.
* Added `sw_settings_payment_detail_custom_field_sets` block in `src/module/sw-settings-payment/page/sw-settings-payment-detail/sw-settings-payment-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-rule/view/sw-settings-rule-detail-base/index.js` to get custom field sets.
* Added `sw_settings_rule_detail_custom_field_sets` block in `src/module/sw-settings-rule/view/sw-settings-rule-detail-base/sw-settings-rule-detail-base.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-salutation/page/sw-settings-salutation-detail/index.js` to get custom field sets.
* Added `sw_settings_salutation_detail_custom_field_sets` block in `src/module/sw-settings-salutation/page/sw-settings-salutation-detail/sw-settings-salutation-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-shipping/page/sw-settings-shipping-detail/index.js` to get custom field sets.
* Added `sw_settings_shipping_detail_custom_field_sets` block in `src/module/sw-settings-shipping/page/sw-settings-shipping-detail/sw-settings-shipping-detail.html.twig` to show custom field sets.
* Added `customFieldSets` variable in `src/module/sw-settings-tax/page/sw-settings-tax-detail/index.js` to get custom field sets.
* Added `sw_settings_tax_detail_custom_field_sets` block in `src/module/sw-settings-tax/page/sw-settings-tax-detail/sw-settings-tax-detail.html.twig` to show custom field sets.
* Changed `custom-fields.service.js` to get custom field sets.
