<?php declare(strict_types=1);

return [
    ['key' => 'id', 'mappedKey' => 'id'],
    ['key' => 'parentId', 'mappedKey' => 'parent_id'],

    ['key' => 'productNumber', 'mappedKey' => 'product_number'],
    ['key' => 'active', 'mappedKey' => 'active'],
    ['key' => 'stock', 'mappedKey' => 'stock'],
    ['key' => 'translations.DEFAULT.name', 'mappedKey' => 'name'],
    ['key' => 'translations.DEFAULT.description', 'mappedKey' => 'description'],

    ['key' => 'price.DEFAULT.net', 'mappedKey' => 'price_net'],
    ['key' => 'price.DEFAULT.gross', 'mappedKey' => 'price_gross'],

    ['key' => 'purchasePrices.DEFAULT.net', 'mappedKey' => 'purchase_prices_net'],
    ['key' => 'purchasePrices.DEFAULT.gross', 'mappedKey' => 'purchase_prices_gross'],

    ['key' => 'tax.id', 'mappedKey' => 'tax_id'],
    ['key' => 'tax.taxRate', 'mappedKey' => 'tax_rate'],
    ['key' => 'tax.name', 'mappedKey' => 'tax_name'],

    ['key' => 'cover.media.id', 'mappedKey' => 'cover_media_id'],
    ['key' => 'cover.media.url', 'mappedKey' => 'cover_media_url'],
    ['key' => 'cover.media.translations.DEFAULT.title', 'mappedKey' => 'cover_media_title'],
    ['key' => 'cover.media.translations.DEFAULT.alt', 'mappedKey' => 'cover_media_alt'],

    ['key' => 'manufacturer.id', 'mappedKey' => 'manufacturer_id'],
    ['key' => 'manufacturer.translations.DEFAULT.name', 'mappedKey' => 'manufacturer_name'],

    ['key' => 'categories', 'mappedKey' => 'categories'],
    ['key' => 'visibilities.all', 'mappedKey' => 'sales_channel'],

    ['key' => 'properties', 'mappedKey' => 'propertyIds'],
    ['key' => 'options', 'mappedKey' => 'optionIds'],
];
