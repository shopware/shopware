---
title: Fix bug invalid data included flow can be saved
issue: NEXT-23844
---
# Administration
* Removed unused snippet in `sw-flow` module, which are used in commercial plugin.
* Changed method `createdComponent` to reset selected invalid ids on `sw-entity-multi-id-select` file.
* Changed method `loadSelected` to reset selected invalid item on `sw-entity-single-select`file.
