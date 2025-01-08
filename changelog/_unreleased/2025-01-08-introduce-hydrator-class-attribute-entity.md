---
title: Introduce Hydrator class on Attribute Entity 
issue: NEXT-00000
author: Raffaele Carelle <raffaele.carelle@gmail.com>
author_github: @raffaelecarelle
---

# Core
 * Changed `\Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity` adding `$hydratoClass` parameter
 * Changed `\Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler` which process `$hydratorClass` parameter
 * Changed `\Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityDefinition` overriding `getHydratorClass` that returns the hydrator class passed on Attribute 
