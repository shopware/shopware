---
title: Fix hot proxy server for shared instances
author: Wolf Wortmann
author_email: wortmann@icue-medien.de
---
# Storefront
* Allow the hot proxy server to be used in shared (multi-domain) environments
    * Added rejection of requests to the hot proxy from the wrong host
    * Added proper error handling inside the hot-proxy-request
