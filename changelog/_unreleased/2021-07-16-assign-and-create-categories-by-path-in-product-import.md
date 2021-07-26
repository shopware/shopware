---
title: Assign and create categories by path in product import
issue: NEXT-16041
flag: FEATURE_NEXT_8097
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `ProductCategoryPathsSubscriber` for parsing paths from column `category_paths`, persisting non-existing categories from path and assigning the leafs to the product
* Changed `ProductVariantsSubscriber` to behave identical by fetching existing properties by name and cache existing and new IDs during the process
