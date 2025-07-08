---
title: Fix slot config inheritance for CMS layout overrides via category
issue: NEXT-33511
author: Marcel Brode
author_email: m.brode@shopware.com
author_github: @Marcel Brode
---
# Core
* Changed `src/Core/Content/Category/SalesChannel/CategoryRoute.php` to fully enable cmsSlotConfig inheritance via category override.
  * Before, changing any cmsElement in the category would disable inheritance for all elements, even unused ones, in the cmsPage, which is now fixed.
___
# Administration
* Changed `src/module/sw-cms/page/sw-cms-detail/index.js` to fix an error, which prevented to save translated CMS pages
