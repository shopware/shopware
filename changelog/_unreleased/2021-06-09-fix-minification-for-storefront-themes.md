---
title: Fix minification for storefront themes
author: mynameisbogdan
author_email: mynameisbogdan@protonmail.com
author_github: mynameisbogdan
---
# Storefront
* Changed `scssphp/scssphp` dependency to version `1.6.0`.
* Changed `Shopware\Storefront\Theme\ThemeCompiler`
  - scssCompiler to use new `setOutputStyle`, with `OutputStyle::COMPRESSED` instead of deprecated `Crunched::class` for non-debug environments.
  - pass `$this->debug` to `Autoprefixer::compile` since by default `$prettyOutput` is set to `true`.
