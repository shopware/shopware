---
title: Use entity name to determine source of slot config in contact form submit
issue: NEXT-15703
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `getEntityName` method to `LandingPage`, `NavigationPage`, `ProductPage`
* Changed getting slot config for contact form to be derived from entity name instead of CMS page type
___
# Storefront
* Changed `cmsPageType` to `entityName` in CMS contact form
