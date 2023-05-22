---
title: Add symplify phpstan naming rules
issue: NEXT-25940
---
# Core
* Added symplify phpstan naming rules
* Deprecated methods `setNextLanguage()` and `setNextDefinition()` in `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset`, use `selectNextLanguage()` or `selectNextDefinition()` instead.
* Deprecated method `\Shopware\Core\Checkout\Document\Renderer\RenderedDocument::setContent()`, it won't return any value in the future.
___
# Next Major Version Changes

## Indexer Offset Changes

The methods `setNextLanguage()` and `setNextDefinition()` in `\Shopware\Elasticsearch\Framework\Indexing\IndexerOffset` are removed, use `selectNextLanguage()` or `selectNextDefinition()` instead.
Before:
```php 
$offset->setNextLanguage($languageId);
$offset->setNextDefinition($definition);
```

After:
```php
$offset->selectNextLanguage($languageId);
$offset->selectNextDefinition($definition);
```
