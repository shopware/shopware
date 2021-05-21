---
title: Remove CORS issue on storefront hot proxy
issue: NEXT-10750
author: Raoul Kramer
author_email: r.kramer@shopware.com 
author_github: @djpogo
---

# Storefront
* Added another regex to rewrite hot proxied responseBody to circumstate CORS issue @see `src/Storefront/Resources/app/storefront/build/proxy-server-hot/index.js`
