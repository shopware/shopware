---
title: Added sales channel favorite management to the admin
issue: NEXT-18049
flag: FEATURE_NEXT_17421
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: bschulzebaek
---
# Administration
* Added `sw-sales-channel/service/sales-channel-favorites.service` to save and fetch favorite sales channels as a user config
* Added cypress command `makeSalesChannelsFavorites` in order to assign all available sales channels as favorites
* Added blocks in file `sw-sales-channel-menu.html.twig`:
  * Added block `sw_sales_channel_menu_navigation_loader` to show a loader when the sales-channel menu list is being loaded
  * Added block `sw_sales_channel_menu_navigation_emptyspace` for when no sales channel was marked as a favorite yet
  * Added block `sw_sales_channel_menu_context_button_collapsed` to show a new link to the sales channel list
