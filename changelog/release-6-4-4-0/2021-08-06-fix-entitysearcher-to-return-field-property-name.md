---
title: Fix EntitySearcher to return field property name
issue: NEXT-16127
---
# Core
* Changed \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher::search to return IdsSearchResult with fields's property name instead of storage name
___
# Upgrade Information
## Change response format of searchIds when using with a mapping entity 

When using `repository.searchIds` method with a mapping entity, it now returns the primary keys pair in camelCase (property name) format instead of snake_case format (storage name).
The storage keys are kept in returned data for now for backwards compatibility but will be deprecated in the next major v6.5.0

Example response of a searchIds request with `product_category` repository:

### Before

```json
{
    "total": 1,
    "data": [
        {
            "product_id": "0f56c10f8c8e41c4acf700e64a481d86",
            "category_id": "7b57ce0d86de4b0da3004e3113b79640"
        }
    ]
}
```

### After

```json
{
    "total": 1,
    "data": [
        {
            "product_id": "0f56c10f8c8e41c4acf700e64a481d86",
            "productId": "0f56c10f8c8e41c4acf700e64a481d86",
            "category_id": "7b57ce0d86de4b0da3004e3113b79640"
            "categoryId": "7b57ce0d86de4b0da3004e3113b79640"
        }
    ]
}
```
