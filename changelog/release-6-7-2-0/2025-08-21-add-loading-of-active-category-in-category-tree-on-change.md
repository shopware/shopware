---
title: Add loading of active category in category tree on change
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Added new `loadActiveCategory` method to `sw-category-tree` component which is called on `category` change to open the new category tree.
* Removed active category loading logic from `openInitialTree` method of `sw-category-tree` component and added a `loadActiveCategory` method call instead.
