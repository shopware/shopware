---
title: Fix sorting on "Newest" to show newest products first
issue: GITHUB-ISSUE-NUMBER
---
# Core
* Added migration `Migration1774483201AddNewestProductSorting` to add default "Newest" product sorting with descending order on `createdAt` field
# Administration
* Changed `sw-settings-listing-option-criteria-grid` component to default time-based fields (`product.createdAt`, `product.releaseDate`) to descending order when creating new sorting criteria
