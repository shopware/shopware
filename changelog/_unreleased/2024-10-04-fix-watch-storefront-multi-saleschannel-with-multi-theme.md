---
title:          Fix watch storefront multi saleschannel with multi theme
issue:          NEXT-38982
author:         Raffaele Carelle
author_e_mail:  raffaele.carelle@gmail.com
author_github:  @raffaelecarelle
---
# Storefront
* Changed the `theme:dump` command to ask for the name of the theme that should be dumped if there isn't a `theme-id` provided via argument and there is more than one theme available.
* Changed the `theme:dump` command to ask for the URL if there are two or more domains available for the sales channel.
* Changed `start-hot-reload.js` to accept the domain URL from the command for the original host domain of the hot-reload server.
