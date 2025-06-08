---
title: Fix image zoom disabled state
issue: NEXT-11578
author: Ramona Schwering
author_email: r.schwering@shopware.com 
author_github: leichteckig
---
# Storefront
* Changed maximum image zoom size in image zoom plugin (`image-zoom.plugin.js`): It should allow zoom button to be enabled and zoom in more than the size of the picture itself by using `multiply(2)`.
