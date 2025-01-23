---
title: Fix `EntitySearchResult` entity types
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed the `extends` annotation in `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult` so it uses the correct template generic type from the parent class
* Removed duplicate methods `filter`, `slice`, `add`, `clear` & `getAt` in `\Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult`
