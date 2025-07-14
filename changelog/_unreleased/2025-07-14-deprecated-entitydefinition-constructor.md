---
title: Deprecated EntityDefinition constructor
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Deprecated constructor of the entity definition `Shopware\Core\Framework\DataAbstractionLayer::__construct`
___
# Upgrade Information
## Removed constructor of the `EntityDefinition`

The constructor of the `EntityDefinition` has been removed, therefore the call of child classes to it should be removed as well, i.e.
```diff
 <?php declare(strict_types=1);

 namespace MyCustomEntity\Content\Entity;

 use Shopware\Core\Content\Media\MediaDefinition;
 use Shopware\Core\Content\Product\ProductDefinition;
 use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;

 class MyCustomEntity extends EntityDefinition
 {
     // snip
 
     public function __construct(private readonly array $meta = [])
     {
-        parent::__construct();
         // ...
     }
 
     // snip
 }
```
