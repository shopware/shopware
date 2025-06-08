---
title: Hot reload improvements for fonts, svg and ddev
issue: NEXT-39321
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `start-hot-reload.js` to properly handle fonts (binary issue) and svg files (chrome, missing content-type)
* Changed `start-hot-reload.js` to set the proxyOption `secure` for DDEV to `false` when skipping the certificate check
* Changed `start-hot-reload.js` improve the workaround for the off-canvas cart and lineItems 
