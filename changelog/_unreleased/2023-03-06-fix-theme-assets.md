---
title: Fix theme assets
issue: NEXT-25650
---
# Storefront
* Theme assets are now stored separately from the compiled js and css files to prevent file duplication for every theme compilation
___
# Upgrade information
## Theme assets are now stored separately from the compiled js and css files
The theme assets are now stored in a separate folder, prefixed with the `theme-id`. They are no longer stored along the compiled js and css files, which means that the assets are not duplicated for every theme compilation.
The `$app-css-relative-asset-path` sccs variable was adapted accordingly to point to the new location of the assets, so if you rely on that variable to get the path to your assets, you don't need to change anything.
