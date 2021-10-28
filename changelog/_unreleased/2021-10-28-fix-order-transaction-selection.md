---
title: Fix order transaction selection in order edit
issue: NEXT-17953
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com 
---
# Administration
* Changed `sw-order-detail-base` to not select failed order transactions as the current transaction
* Changed `sw-order-detail-general` to not select failed order transactions as the current transaction
* Changed `sw-order-detail-detail` to not select failed order transactions as the current transaction
* Changed `sw-order-general-info` to not select failed order transactions as the current transaction
* Changed `sw-order-state-history-card` to not select failed order transactions as the current transaction
* Changed `sw-order-list` to not select failed order transactions as the current transaction
