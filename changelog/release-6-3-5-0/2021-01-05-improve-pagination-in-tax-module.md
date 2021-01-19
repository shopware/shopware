---
title: improve pagination in tax-module
issue: NEXT-12065 
author: Niklas Limberg
author_email: n.limberg@shopware.com 
author_github: NiklasLimberg
---
# Administration
* Added `<pagination/>` to `module/sw-settings-tax/component/sw-tax-rule-card/sw-tax-rule-card.html.twig`
* Changed the `taxRuleCriteria` prop to sort by `country.name` and `type.position` when sorting by `country.name` otherwise sort by the collum that was clicked on in `module/sw-settings-tax/component/sw-tax-rule-card/index.js`
* Added the methods `paginate` and `onColumnSort` to react to page change and sort event in `module/sw-settings-tax/component/sw-tax-rule-card/index.js`