---
title:              Optimize foreign key resolver
issue:              NEXT-10547
author:             Oliver Skroblin
author_email:       o.skroblin@shopware.com
author_github:      @OliverSkroblin
---
# Core
* Changed result of `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver`.   
* Changed the foreign keys to be determined per association using a separate query. The entity foreign key resolver has previously executed a single query to determine the foreign keys of the associations. This leads to very slow queries for large amounts of data, which block the database.
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryHelper::addIdCondition` use `se \Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper::addIdCondition` instead
___
# Upgrade Information
## Entity Foreign Key Resolver
There are currently systems that have performance problems with the `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver`.
We have now created a solution for this, but we have to change the format of the return value of the different functions as follow:

### getAffectedDeleteRestrictions & getAffectedDeletes
* `EntityForeignKeyResolver::getAffectedDeleteRestrictions`
* `EntityForeignKeyResolver::getAffectedDeletes`

**before**
```
[
    [
        'pk' => '43c6baad756140d8aabbbca533a8284f'
        restrictions => [
            "order_customer" => array:2 [
                "cace68bdbca140b6ac43a083fb19f82b",
                "50330f5531ed485fbd72ba016b20ea2a",
            ]
            "order_address" => array:4 [
                "29d6334b01e64be28c89a5f1757fd661",
                "484ef1124595434fa9b14d6d2cc1e9f8",
                "601133b1173f4ca3aeda5ef64ad38355",
                "9fd6c61cf9844a8984a45f4e5b55a59c",
            ]
        ]
    ]
]
```

**after** 
```
[
    "order_customer" => array:2 [
        "cace68bdbca140b6ac43a083fb19f82b",
        "50330f5531ed485fbd72ba016b20ea2a",
    ]
    "order_address" => array:4 [
        "29d6334b01e64be28c89a5f1757fd661",
        "484ef1124595434fa9b14d6d2cc1e9f8",
        "601133b1173f4ca3aeda5ef64ad38355",
        "9fd6c61cf9844a8984a45f4e5b55a59c",
    ]
]
```

### getAffectedSetNulls
* `EntityForeignKeyResolver::getAffectedSetNulls`

**before**
```
[
    [
        'pk' => '43c6baad756140d8aabbbca533a8284f'
        restrictions => [
            'Shopware\Core\Content\Product\ProductDefinition' => [
                '1ffd7ea958c643558256927aae8efb07' => ['category_id'],
                '1ffd7ea958c643558256927aae8efb07' => ['category_id', 'main_category_id']
            ]
        ]
    ]
]
```               

**after**
```
[
    'product.manufacturer_id' => [
        '1ffd7ea958c643558256927aae8efb07',
        '1ffd7ea958c643558256927aae8efb07'
    ],
    'product.cover_id' => [
        '1ffd7ea958c643558256927aae8efb07'
        '1ffd7ea958c643558256927aae8efb07'
    ]
]
```
