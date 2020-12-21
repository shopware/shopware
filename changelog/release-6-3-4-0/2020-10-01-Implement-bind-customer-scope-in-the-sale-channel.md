---
title: Implement bind customer scope in the SaleChannel
issue: NEXT-10974   
---
# Core
* Added a new system config file: `src\Core\System\Resources\config\systemWideLoginRegistration.xml`.
* Added a new `isCustomerBoundToSalesChannel` boolean `<input-field />` into `src\Core\System\Resources\config\systemWideLoginRegistration.xml`  
___
# Administration
* Added `{% block sw_setting_login_registration_system_wide %}` in `module\sw-settings-login-registration\page\sw-settings-login-registration\sw-settings-login-registration.html.twig`
* Added `{% block sw_customer_base_metadata_bound_sales_channel %}` in `module\sw-customer\component\sw-customer-base-info\sw-customer-base-info.html.twig`
* Changed method `getCustomerColumns` in `/module/sw-customer/page/sw-customer-list/index.js`
* Added `{% block sw_customer_list_grid_columns_boundSalesChannel %}` in `module\sw-customer\page\sw-customer-list\sw-customer-list.html.twig`
* Added watcher `customer.salesChannelId` in `/module/sw-customer/page/sw-customer-create/index.js`
