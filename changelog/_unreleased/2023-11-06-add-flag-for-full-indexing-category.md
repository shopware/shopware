---
title: add-flag-for-full-indexing-category
issue: NEXT-18681
author: Alexandru Dumea
author_email: a.dumea@shopware.com
author_github: Alexandru Dumea
---

# Core
* Added a new isFullIndexing flag to the `EntityIndexingMessage` class. This enhancement allows developers to specify whether a full re-indexing is required or just a single entity was updated inside the stack
* Added optional (hidden) parameter `bool $recursive` to `TreeUpdater::batchUpdate`. Parameter will be introduced in the next major version 
___
# Upgrade Information
## EntityIndexingMessage::isFullIndexing

We added a new `isFullIndexing` flag to the `EntityIndexingMessage` class. 
When entities will be updated, the flag is marked with `false`. It will be marked with `true` via `bin/console dal:refresh:index` or other APIs which triggers a full re-index.
This enhancement allows developers to specify whether a full re-indexing is required or just a single entity was updated inside the stack

```
<?php

class Indexer extends ...
{
    public function index(EntityIndexingMessage $message) 
    { 
        $message->isFullIndexing()
    }
}
```

We also added a new optional (hidden) parameter `bool $recursive` to `TreeUpdater::batchUpdate`. This parameter will be introduced in the next major version. 
If you extend the `TreeUpdater` class, you should properly handle the new parameter in your custom implementation.
Within the 6.6 release, the parameter is optional and defaults to `true`. It will be changed to `false` in the next major version.
```php
<?php

class CustomTreeUpdater extends TreeUpdater
{
    public function batchUpdate(array $updateIds, string $entity, Context $context/*, bool $recursive = false*/): void
    {
        $recursive = func_get_arg(3) ?? true;
        
        parent::batchUpdate($updateIds, $entity, $context, $recursive);
    }
}
```

___
# Next Major Version Changes
## TreeUpdater::batchUpdate

We added a new optional parameter `bool $recursive` to `TreeUpdater::batchUpdate`.
If you extend the `TreeUpdater` class, you should properly handle the new parameter in your custom implementation.
```php
<?php

class CustomTreeUpdater extends TreeUpdater
{
    public function batchUpdate(array $updateIds, string $entity, Context $context, bool $recursive = false): void
    {
        parent::batchUpdate($updateIds, $entity, $context, $recursive);
    }
}
```
