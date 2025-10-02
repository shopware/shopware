---
title: Meta information in landing pages is not inherited
issue: https://github.com/shopware/shopware/issues/3712
author: Stefan Reichelt
author_email: stefan@kreativsoehne.de
author_github: @Songworks
---
# Storefront
* Changed `LandingPageLoader` to retrieve meta information (metaTitle, metaDescription & keywords) through translations, for proper language inheritance
* Deprecated `PageNotFoundException` in `LandingPageLoader` for v6.8.0, in favour of `LandingPageException::notFound`
