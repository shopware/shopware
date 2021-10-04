---
title: Fixed today not showing in admin dashboard statistics if there is no order yet
issue: NEXT-16188
author: Eric Heinzl
author_email: e.heinzl@shopware.com 
---
# Administration
* Added dummy bucket to `administration/src/module/sw-dashboard/page/sw-dashboard-index/index.js:historyOrderData` if there is no order for today. Otherwise, today won't be displayed without order.
