---
title: Cleanup ACL rules for default layouts
issue: NEXT-37298
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Changed context menu of `sw-cms-list` items to allow to set default product layouts directly from the listing
* Changed `sw-cms-sidebar` to only show the default option when the layout is not already the default layout and add a notification if the layout is the default
* Changed required ACL of `sw-cms-list` and `sw-cms-sidebar` permissions from `system_config.editor` to `system_config:{read,update,create,delete}`
* Changed `sw-cms-layout-modal` to load the default layouts if ACL permission `system_config:read` is given instead of `system_config.read`
* Changed `sw-cms-detail` to load the default layouts if ACL permission `system_config:read` is given instead of `system_config.read`
* Changed `sw-cms-list` to load the default layouts if ACL permission `system_config:read` is given instead of `system_config.read`
* Changed `sw-cms-list` to only load user settings if `user_config:read` ACL permission is given
* Changed `sw-cms-list` to only persist the user settings if permissions `user_config:create` and `user_config:update` are given
