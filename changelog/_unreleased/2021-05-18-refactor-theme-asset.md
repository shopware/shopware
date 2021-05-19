---
title: Refactor theme asset
issue: NEXT-14699
flag: FEATURE_NEXT_14699
---

# Storefront
* Changed `\Shopware\Storefront\Theme\ThemeFileImporter::getCopyBatchInputsForAssets` to copy **only** to `public/theme`
* Changed `theme` asset to contain theme prefix url to use it easier

___
# Upgrade Information

## Storefront theme asset refactoring

In previous Shopware versions the theme assets has been copied to both folders `bundles/[theme-name]/file.png` and `theme/[id]/file.png`.
This was needed to be able to link the asset in the Storefront as the theme asset doesn't include the theme path prefix.

To improve the performance of `theme:compile` and to reduce the confusion of the usage of assets we copy the files only to `theme/[id]`.

To use the updated asset package replace your current `{{ asset('logo.png', '@ThemeName') }}` with `{{ asset('logo.png', 'theme'') }}`
