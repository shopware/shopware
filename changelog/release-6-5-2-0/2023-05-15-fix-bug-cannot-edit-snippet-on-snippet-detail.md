---
title: Fix bug missing snippets input when snippets set is more than 25
issue: NEXT-25322
author: Tam Dao
author_email: t.dao@shopware.com
---
# Administration
* Changed `snippetSetCriteria` in `src/Administration/Resources/app/administration/src/module/sw-customer/page/sw-customer-detail/index.js` to update the limit number of snippet set criteria is null.
