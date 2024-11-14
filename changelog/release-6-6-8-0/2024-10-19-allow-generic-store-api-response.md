---
title: Allow generic store api response
issue: NEXT-39169
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---
# Core
* Removed `abstract` declaration from `\Shopware\Core\System\SalesChannel\StoreApiResponse` which allows to use the class as a generic response object for store api responses. 
