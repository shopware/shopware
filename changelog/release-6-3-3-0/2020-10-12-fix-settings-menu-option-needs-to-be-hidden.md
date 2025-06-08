---
title: Fix settings menu option needs to be hidden
issue: NEXT-11283
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Changed State commit in `sw-admin-menu` because the commit name was wrong. This fixes an issue that the first acl checks are false positive which causes requests errors.
* Added an acl check in `customer-group-registration-listener.service`
* Added checks for each settings tab if some items are visible for the user. If this is not the case then the user can´t see the tab.
