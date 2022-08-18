---
title: Fix scssphp deprecation
issue: NEXT-19429
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Changed `Resources/app/storefront/src/scss/vendor/_datepicker.scss` and removed `.css` extension from `@import "~vendor/flatpickr/dist/flatpickr.min"`. Importing CSS with explicit file extension is deprecated and will not be supported anymore in ScssPhp 2.0.
* Changed `\Shopware\Storefront\Theme\ThemeCompiler::compileStyles` in order to interpret `~vendor`-alias imports which use the `.min` extension as CSS
