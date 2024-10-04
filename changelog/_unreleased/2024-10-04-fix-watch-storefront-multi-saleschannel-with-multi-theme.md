---
title:          Fix watch storefront multi saleschannel with multi theme
issue:          NEXT-0000
author:         Raffaele Carelle
author_e_mail:  raffaele.carelle@gmail.com
author_github:  @raffaelecarelle
---
# Storefront
* Changed `theme:dump`: if there isn't a `theme-id` as argument and there are 2 or more themes, the name of the theme want to dump is asked.
* Changed `theme:dump`: if there are 2 or more domain url for theme, the url is asked.
* Changed `start-hot-reload.js` to accept the domain url choose above as original host domain of hot server.
