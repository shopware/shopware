---
title: Add cache for different Json Responses
issue: NEXT-12049
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
*  Changed route `frontend.detail.switch` in `ProductController.php` to set header for correct HttpCache usage
*  Changed route `widgets.search.filter` in `SearchController.php` to set header for correct HttpCache usage
*  Changed route `frontend.cms.navigation.filter` in `CmsController.php` to set header for correct HttpCache usage
