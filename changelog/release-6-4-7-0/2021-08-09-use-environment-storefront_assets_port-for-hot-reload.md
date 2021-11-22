---
title: Use environment STOREFRONT_ASSETS_PORT for hot reload
issue: NEXT-17813
author: Manuel Selbach
author_email: m.selbach@kellerkinder.de 
author_github: manuelselbach
---
# Storefront

* With this change the environment variable STOREFRONT_ASSETS_PORT is also used for the hot reload server and it falls back to the static value `9999` like it is done everywhere else in the webpack configuration, to be able to actually use a custom port.
