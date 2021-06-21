---
title: sync-the-app-url-outdated-state-on-shopid-read
issue: NEXT-15488

 
---
# Core
*  Changed the ShopIdProvider to now check at every call of getShopId() if the app_url was marked outdated in the past.
