---
title: Implement Custom Field entity select for dynamic product groups
issue: NEXT-33234
author: Rafael Kraut
author_email: 14234815+RafaelKr@users.noreply.github.com
author_github: RafaelKr
___
# Administration
* Changed the dynamic product group condition component `module/sw-product-stream/component/sw-product-stream-value` to show a `sw-entity-single-select` or `sw-entity-multi-id-select` for custom fields of type `'entity'`. Previously this rendered a text field where you had to insert an UUID. 
