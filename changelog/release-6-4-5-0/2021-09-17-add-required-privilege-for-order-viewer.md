---
title: Add required privilege for order viewer
issue: NEXT-17288
---
# Core
* Added a migration to add `log_entry:create` required privilege for existing users and `state_machine_state:read` for `order.viewer` role
* Added a migration to add `order_tag:read` privilege for existing `order.viewer` user and `order_tag:create`, `order_tag:update`, `order_tag:delete` for `order.editor` role
___
# Administration
* Added new privilege `state_machine_transition:read` for `order.viewer` in `src/module/sw-order/acl/index.js`
* Added new privilege `order_tag:read` for `order.viewer` in `src/module/sw-order/acl/index.js`
* Added new privileges `order_tag:create`, `order_tag:update`, `order_delete` for `order.editor` in `src/module/sw-order/acl/index.js`
* Added new required privilege `log_entry:create` in `src/app/service/privileges.service.js`
