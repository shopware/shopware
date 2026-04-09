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

## Deprecation of `EntityDefinition` constructor

The constructor of the `EntityDefinition` will be removed, therefore the call of child classes to it should be removed as well, i.e:
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

# Next Major Version Changes

## Removal of `EntityDefinition` constructor

The constructor of the `EntityDefinition` has been removed, therefore the call of child classes to it need to be removed as well, i.e:
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
