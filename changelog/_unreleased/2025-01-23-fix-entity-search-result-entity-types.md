---
title: Fix `EntitySearchResult` entity types
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `@extends EntityCollection` in `Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult` to use the correct template generic type & removed duplicate methods from EntityCollection
