---
title: Added option to duplicate product streams in administration
issue: NEXT-13561
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `CascadeDelete` flag to `filters` association field in `ProductStreamDefinition`
___
# Administration
* Added block `sw_product_stream_list_grid_more_actions` and `sw_product_stream_list_grid_duplicate_action` in template of `sw-produc-stream-list`
* Added method `onDuplicate` in `sw-produc-stream-list` component
