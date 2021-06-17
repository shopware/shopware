---
title: Added a twig extension to load the entities
issue: NEXT-14366
---
# Core
* Added a new twig extension `EntityExtension` to `Shopware\Core\Framework\Adapter\Twig\Extension` to load the entities from twig file. For example: `{% set products = entities('product', [id-1, id-2, id-3], ['manufacturer', 'options.group']) %}`
