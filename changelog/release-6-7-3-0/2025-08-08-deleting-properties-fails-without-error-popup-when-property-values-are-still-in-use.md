---
title: Deleting properties fails without error popup when property values are still in use
issue: #11721
author: Le Nguyen
author_email: nguyenquocdaile@gmail.com
author_github: @nguyenquocdaile
---
# Administration
* Changed `onConfirmDelete` method to show error popup when deleting a property fails in `src/module/sw-property/page/sw-property-list/index.js`.
