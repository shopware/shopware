---
title: Resolve fonts relative to the theme app.css
issue: NEXT-10560
---
# Storefront
* Fixed font urls in case that `APP_URL` and the storefront domain differ and no url is defined for the asset filesystem 
* Added scss variable `$app-css-relative-asset-path`, which is the relative path from the generated `app.css` in the `public/theme/<theme-id>` to the asset folder.
  You can use it to reference assets inside scss.
* Deprecated SCSS variable `$asset-path`, use `$app-css-relative-asset-path` instead
___
# Upgrade Information
## References to assets inside themes

We've deprecated `$asset-path` because it was generated at compilation time and contained the `APP_URL` by default,
which should only be relevant to administration. Instead, `$app-css-relative-asset-path` is to be used, which is an
url that is relative to the `app.css` that points to the asset folder.

As a side effect, the fonts are now loaded from the theme folder instead of the bundle asset folder. This should work out of the box,
because all assets of the theme are also copied into the theme folder.
