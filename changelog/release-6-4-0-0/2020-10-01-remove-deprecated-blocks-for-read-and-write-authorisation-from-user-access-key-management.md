---
title: Remove deprecated blocks for read and write authorisation from user access key management
issue: NEXT-10826
author: Philip Gatzka
author_email: p.gatzka@shopware.com 
author_github: @philipgatzka
---
# Administration
* Removed the following blocks from `src/module/sw-users-permissions/page/sw-users-permissions-user-detail/sw-users-permissions-user-detail.html.twig`
 - `sw_settings_user_detail_key_grid_column_write_access`
 - `sw_settings_user_detail_detail_modal_inner_field_read_access`
 - `sw_settings_user_detail_detail_modal_inner_field_write_access`
