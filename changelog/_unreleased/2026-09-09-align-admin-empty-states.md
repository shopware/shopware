---
title: Align admin empty states
author: Fabian Hüske
author_email: f.hueske@shopware.com
author_github: @fabianhueske
---
# Administration
* Changed the remaining prominent admin empty states from `sw-empty-state`, plain text or image markup to the centered `mt-empty-state` component with the icon of the module the empty state belongs to
* Added a create action to the empty state of the following list pages, shown when no entity exists yet and disabled without the `creator` privilege: customers, flows, integrations, manufacturers, orders, dynamic product groups, products, properties, sales channels, custom field sets, number ranges, rules, shipping methods, tags
* Changed the following Twig blocks to empty anchors that render outside the empty state, as the icon, image or label became `mt-empty-state` props. Overriding them still compiles, but the content is no longer placed inside the empty state:
  * `sw_flow_list_empty_state_icon` in `sw-flow-list`
  * `sw_mail_header_footer_list_grid_empty_state_icon` in `sw-mail-header-footer-list`
  * `sw_mail_template_list_grid_empty_state_icon` in `sw-mail-template-list`
  * `sw_order_create_address_modal_empty_state_content` in `sw-order-create-address-modal`
  * `sw_order_customer_grid_empty_state_icon` in `sw-order-customer-grid`
  * `sw_promotion_v2_individual_codes_behavior_empty_state_icon` in `sw-promotion-v2-individual-codes-behavior`
  * `sw_sales_channel_products_assignment_dynamic_product_groups_listing_empty_icon` in `sw-sales-channel-products-assignment-dynamic-product-groups`
  * `sw_settings_listing_option_criteria_card_empty_state_icon` in `sw-settings-listing-option-criteria-grid`
  * `sw_settings_listing_content_card_view_options_card_empty_state_icon` in `sw-settings-listing`
  * `sw_product_feature_set_card_empty_state_image` and `sw_product_feature_set_card_empty_state_label` in `sw-settings-product-feature-sets-values-card`
  * `sw_tax_rule_card_empty_state_image` and `sw_tax_rule_card_empty_state_label` in `sw-tax-rule-card`
* Changed the block `sw_promotion_v2_individual_codes_behavior_empty_state_actions` in `sw-promotion-v2-individual-codes-behavior` to fill the `button` slot of `mt-empty-state` instead of the `actions` slot of `sw-empty-state`. Overrides of this block have to use `<template #button>`
* Deprecated the listed Twig block anchors, they will be removed in v6.8.0
___
# Upgrade Information
## Empty state Twig blocks moved to `mt-empty-state` props
The `sw-empty-state` icon, image and label blocks listed above no longer wrap markup inside the empty state. Pass a custom icon through the `icon` prop of `mt-empty-state` by overriding the surrounding `*_empty_state` block instead of the icon block. Overrides of `sw_promotion_v2_individual_codes_behavior_empty_state_actions` have to switch from `<template #actions>` to `<template #button>`.
