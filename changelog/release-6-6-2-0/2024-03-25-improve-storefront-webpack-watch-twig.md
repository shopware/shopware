---
title: Improve storefront webpack watch twig
issue: NEXT-35041
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Storefront
* Added the `SHOPWARE_STOREFRONT_SKIP_EXTENSION_TWIG_WATCH` variable to disable twig watch of extensions
* Changed the `webpack.config.js` to additionally include `.twig` files of extensions in the watch paths
