---
title: Assign newly created layout automatically
issue: NEXT-6944
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Changed `sw-category-layout-card/index.js` to pass the category id when creating a new layout
* Changed `sw-cms-create/index.js` to assign the layout to a category if it was initiated form a category
* Changed `sw-cms/index.js` to allow passing the type and the id of the entity to be assigned
