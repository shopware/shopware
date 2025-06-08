---
title: Add first sales channel default selection for products to First Run Wizard
issue: NEXT-187000
---
# Administration
* Added component `sw-settings-listing-default-sales-channel`, which is extracted from `sw-settings-listing-index` to be re-usable
* Added First Run Wizard page component `sw-first-run-wizard-defaults` to handle default Sales Channels for products
* Changed First Rund Wizards stepper state rendering, to be rendered dynamically for all entries instead of a hardcoded 2-dimensional array
* Deprecated serveral elements in `app/administration/src/module/sw-settings-listing/page/sw-settings-listing/index.js` use those elements in `sw-settings-listing-default-sales-channel` instead:
  * Data properties:
    * `displayVisibilityDetail` 
    * `configData`
    * `visibilityConfig`
  * Computed properties:
    * `salesChannel`
  * Blocks - Temporarily moved to `sw-settings-listing-default-sales-channel` until deletion:
    * `sw_settings_listing_content_card_view_default_sales_channel_description`
    * `sw_settings_listing_content_card_view_default_sales_channel_select`
    * `sw_settings_listing_content_card_view_default_sales_channel_setting`
    * `sw_settings_listing_content_card_view_default_sales_channel_setting_active`
  * Blocks - Remaining in `sw-settings-listing`:
    * `sw_settings_listing_content_card_view_default_sales_channel_setting_visibility`
    * `sw_settings_listing_content_card_view_default_sales_channel_select_visibiliy_modal`
  * Methods:
    * `fetchSalesChannelsSystemConfig()`
    * `displayAdvancedVisibility()`
    * `closeAdvancedVisibility()`
    * `updateSalesChannel()`
    * `saveSalesChannelVisibilityConfig()`
