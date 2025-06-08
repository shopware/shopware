---
title: Unset existing price field percentage if list price is removed
issue: NEXT-18611
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer::encode` to unset ratio percentage if list price is not set or null
