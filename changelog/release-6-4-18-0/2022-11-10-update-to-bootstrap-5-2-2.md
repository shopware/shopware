---
title: Update to Bootstrap 5.2.2
issue: NEXT-22818
author: Tobias Berge
author_email: t.berge@shopware.com
author_github: @tobiasberge
---
# Storefront
* Changed `bootstrap5` version from `5.1.3` to `5.2.2`
* Changed `@popperjs/core` version from `2.10.2` to `2.11.6`
* Deprecated usage of `btn-block`, using `d-grid` wrapper instead in `Resources/views/storefront/component/buy-widget/buy-widget-form.html.twig`
* Deprecated usage of SCSS function `color-yiq`, using `color-contrast` instead in `Resources/app/storefront/src/scss/component/_icon.scss`
* Deprecated `.show` class styling on `.dropdown` wrapper, apply styling on dropdown trigger instead in `Resources/app/storefront/src/scss/component/_filter-panel.scss`
