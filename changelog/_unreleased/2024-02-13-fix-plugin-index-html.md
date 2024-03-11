---
title: Fix plugin index.html
issue: NEXT-33455
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Core
* Changed `\Shopware\Core\Framework\Api\Controller\InfoController::getBaseUrl` to return Symfony route `administration.plugin.index`
* Changed `\Shopware\Core\Framework\Api\EventListener\ResponseHeaderListener` no longer checks for `X-FRAME-OPTIONS` header in response
* Changed `\Shopware\Core\Framework\Routing\CoreSubscriber` to only add `X-Frame-Options` header to response if it is not already set
___
# API
* Added `administration.plugin.index` route
___
# Administration
* Changed `webpack.config.js` to use `HtmlWebpackPlugin` to inject `base` tag into `index.html`
