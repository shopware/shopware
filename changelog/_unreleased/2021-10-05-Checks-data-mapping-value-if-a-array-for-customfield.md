---
title: Checks data mapping value if a array. needed for customfield integration
issue: NEXT-11407
author: Benjamin Ott
author_github: @ottscho
---

---
# Core
* Changed `\Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver::resolveEntityValue()` checks if entity a array or object. if data mapping use a customfield, then is the value/entity a array.
___
