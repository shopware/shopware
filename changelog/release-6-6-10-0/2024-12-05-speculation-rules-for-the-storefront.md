---
title: Speculation Rules for the Storefront
issue: NEXT-39921
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Added an experimental Speculation Rules API JavaScript Plugin to the Storefront (see `src/Storefront/Resources/app/storefront/src/plugin/speculation-rules/speculation-rules.plugin.js`).
  * By default, this new functionality is disabled.
  * To enable it, log in to the administration, navigate to `Settings > System > Storefront`, and set the "Speculation rules API" option to true/active.
  * In the javascript plugin we check if the browser [supports the Speculation Rules API](https://caniuse.com/mdn-http_headers_speculation-rules) and only add the script tag if so.
  * For the [eagerness option](https://developer.chrome.com/docs/web-platform/prerender-pages#eagerness) we are using `moderate` everywhere. That means a user must interact with a link to execute the pre-rendering.
  * Keep in mind that pre-rendering is putting extra load on your server and also can affect your [analytics](https://developer.chrome.com/docs/web-platform/prerender-pages#impact-on-analytics).
