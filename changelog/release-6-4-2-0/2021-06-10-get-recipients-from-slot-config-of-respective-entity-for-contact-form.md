---
title: Get recipients from slot config of respective entity for contact form
issue: NEXT-14222
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added property `navigationId` to `NavigationPage`, `LandingPage` and `ProductPage` which is set to the ID of the category, landing page or product respectively
* Changed `ContactFormRoute` to determine repository of entities to get the slot config from by CMS page type
___
# Storefront
* Changed CMS element contact form to pass id of either category page, landing page or product to retreive the respective slot config with specific recipients
* Added hidden input to CMS element contact form to pass type of CMS page
