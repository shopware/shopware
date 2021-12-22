---
title: Make payments distinguishable in certain areas in the Administration
issue: NEXT-15170
flag: FEATURE_NEXT_15170
---
# Core
* Added new translated field `distinguishableName` in `\Shopware\Core\Checkout\Payment\PaymentMethodDefinition::defineFields()`
* Added new field `distinguishableName` in `\Shopware\Core\Checkout\Payment\PaymentMethodTranslationDefinition::defineFields()`
* Added new class `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber`
* Fixed unused imports in `src/Core/Framework/Migration/Template/MigrationTemplateLegacy.txt`
* Added migration `\Shopware\Core\Migration\V6_4\Migration1620733405DistinguishablePaymentMethodName`
* Added migration `\Shopware\Core\Migration\V6_4\Migration1620733405UpdateRolePrivilegesForDistinguishablePaymentName`
___
# API
* Added new write-protected runtime field `distinguishableName` to `/api/search/payment-method`
___
# Administration
* Added new computed property `paymentMethodLabelProperty` in `src/module/sw-customer/component/sw-customer-base-info/index.js` and `src/module/sw-customer/component/sw-customer-base-info/sw-customer-base-info.html.twig`
* Changed block `sw_customer_base_metadata_default_payment_content` to show `customer.defaultPaymentMethod.translated.distinguishableName` in `src/module/sw-customer/component/sw-customer-base-info/sw-customer-base-info.html.twig`
* Changed `<sw-entity-single-select>` in block `sw_order_create_details_footer_payment_method` to use `labelProperty="distinguishableName"` in `src/module/sw-order/component/sw-order-create-details-footer/sw-order-create-details-footer.html.twig`
* Changed `<sw-entity-single-select>` in block `sw_condition_payment_method_field_payment_method_ids` to use `labelProperty="distinguishableName"` in `src/app/component/rule/condition-type/sw-condition-payment-method/sw-condition-payment-method.html.twig`
* Changed block `sw_order_detail_base_secondary_info_payment` to show `paymentMethod.translated.distinguishableName` in `src/module/sw-order/component/sw-order-user-card/sw-order-user-card.html.twig`
* Changed `privileges` of role `sales_channel.viewer` to require privileges of `payment.viewer` in `src/module/sw-sales-channel/acl/index.js`
* Added new computed property `labelProperty` in `src/module/sw-sales-channel/component/sw-sales-channel-defaults-select/index.js`
* Changed `<sw-entity-multi-select>` to use new computed property `labelProperty` in `src/module/sw-sales-channel/component/sw-sales-channel-defaults-select/sw-sales-channel-defaults-select.html.twig`
* Changed `getPaymentMethodUsage()` from `sw-media-quickinfo-usage`-component, to use the distinguishableName instead of the name property of the payment method
* Added privileges `app:read` and `app_payment_method:read` to role `payment.viewer` in `src/module/sw-settings-payment/acl/index.js`
* Added associations `plugin` and `appPaymentMethod.app` in `src/module/sw-settings-payment/page/sw-settings-payment-list/index.js`
* Added new column `extension` to `getPaymentColumns()` in `src/module/sw-settings-payment/page/sw-settings-payment-list/index.js`
* Added new block `sw_settings_payment_list_column_extension` in `src/module/sw-settings-payment/page/sw-settings-payment-list/sw-settings-payment-list.html.twig`
* Added new translation `sw-settings-payment.list.columnExtension` in `src/module/sw-settings-payment/snippet/de-DE.json`
* Added new translation `sw-settings-payment.list.columnExtension` in `src/module/sw-settings-payment/snippet/en-GB.json`
___
# Upgrade Information
If you display payment methods in the administration, please use the new `paymentMethod.translated.distinguishableName` property instead of the `name` property, so that the same payment methods provided by different extensions can be easily distinguished by the user.
