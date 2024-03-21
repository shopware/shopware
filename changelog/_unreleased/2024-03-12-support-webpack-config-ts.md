---
title: Support webpack config ts & cts files
issue: NEXT-00000
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `getWebpackConfig` method in `BundleConfigGenerator` to also check for `webpack.config.ts` & `webpack.config.cts`
