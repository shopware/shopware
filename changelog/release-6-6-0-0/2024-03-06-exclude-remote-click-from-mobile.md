---
title: Exclude remote click plugin from mobile view
issue: NEXT-34165
---
# Storefront
* Added new option `excludedViewports` to `Resources/app/storefront/src/plugin/remote-click/remote-click.plugin.js` to exclude viewports.
* Changed `Storefront/Resources/views/storefront/component/buy-widget/buy-widget.html.twig` by adding `XS` viewport to be excluded in review link.
