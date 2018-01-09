<?php declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/../../.env');

require_once __DIR__ . '/common/ColumnDefinition.php';
require_once __DIR__ . '/common/TableDefinition.php';
require_once __DIR__ . '/common/Context.php';
require_once __DIR__ . '/common/Util.php';
require_once __DIR__ . '/common/Associations.php';
require_once __DIR__ . '/StructureCollector.php';
require_once __DIR__ . '/struct/StructGenerator.php';
require_once __DIR__ . '/event/EventGenerator.php';
require_once __DIR__ . '/repository/RepositoryGenerator.php';
require_once __DIR__ . '/searchresult/SearchResultGenerator.php';
require_once __DIR__ . '/definition/DefinitionGenerator.php';
require_once __DIR__ . '/collection/CollectionGenerator.php';
require_once __DIR__ . '/ApiGenerator.php';

$inBasic = [
    'shop' => ['locale', 'currency'],
    'product' => ['unit', 'price', 'manufacturer', 'tax', 'listingPrice'],
    'order' => ['customer', 'state', 'paymentMethod', 'currency', 'shop', 'billingAddress'],
    'order_address' => ['country', 'countryState'],
    'order_delivery' => ['orderState', 'shippingAddress', 'shippingMethod'],
    'order_delivery_position' => ['orderLineItem'],
    'product_media' => ['media'],
    'product_price' => ['customerGroup'],
    'shipping_method' => ['price'],
    'media' => ['album'],
    'product_listing_price' => ['customerGroup'],
    'category' => ['productStream', 'media'],
    'customer' => ['group', 'defaultShippingAddress', 'defaultBillingAddress', 'lastPaymentMethod', 'defaultPaymentMethod', 'shop'],
    'customer_address' => ['country', 'countryState'],
    'product_stream' => ['listingSorting'],
];

$associations = [
    new ManyToManyAssociation('category', 'product', 'product', 'product_category'),
    new ManyToManyAssociation('product', 'category', 'category', 'product_category'),

    new ManyToManyAssociation('product', 'category', 'seoCategory', 'product_seo_category'),
    new ManyToManyAssociation('category', 'product', 'seoProduct', 'product_seo_category'),

    new ManyToManyAssociation('shop', 'currency', 'currency', 'shop_currency'),
    new ManyToManyAssociation('currency', 'shop', 'shop', 'shop_currency'),

    new ManyToManyAssociation('product', 'product_stream', 'tab', 'product_stream_tab'),
    new ManyToManyAssociation('product_stream', 'product', 'productTab', 'product_stream_tab'),

    new ManyToManyAssociation('product', 'product_stream', 'stream', 'product_stream_assignment'),
    new ManyToManyAssociation('product_stream', 'product', 'product', 'product_stream_assignment'),
];

$prevent = [
    'shop' => ['snippet', 'templateConfigFormFieldValue', 'productSeoCategory', 'customer', 'order', 'seoUrl',  'mailAttachment', 'configFormFieldValue', 'productSearchKeyword'],
    'locale' => ['configFormFieldTranslation', 'configFormTranslation'],
    'customer_address' => ['customers'],
    'customer_group' => [
        'productListingPrices',
        'productPrices',
        'shippingMethods',
        'shops',
        'taxAreaRules',
    ]
];

$inject = [
    'media' => [
        'struct' => file_get_contents(__DIR__ . '/special_cases/media/struct.txt')
    ],
    'customer' => [
        'struct' => file_get_contents(__DIR__ . '/special_cases/customer/struct.txt')
    ],
    'seo_url' => [
        'collection' => file_get_contents(__DIR__ . '/special_cases/seo_url/collection.txt'),
        'struct' => file_get_contents(__DIR__ . '/special_cases/seo_url/struct.txt')
    ],
    'category' => [
        'collection' => file_get_contents(__DIR__ . '/special_cases/category/collection.txt'),
        'struct' => file_get_contents(__DIR__ . '/special_cases/category/struct.txt')
    ],
    'shop' => [
        'collection' => file_get_contents(__DIR__ . '/special_cases/shop/collection.txt'),
    ],
    'customer_group_discount' => [
        'collection' => file_get_contents(__DIR__ . '/special_cases/customer_group_discount/collection.txt'),
    ],
    'order' => [
        'definition' => file_get_contents(__DIR__ . '/special_cases/order/write_order.txt'),
    ]
];

$virtualForeignKeys = [
    'customer' => [
        'default_shipping_address_id' => ['customer_address', 'id'],
        'default_billing_address_id' => ['customer_address', 'id']
    ]
];

$htmlFields = [
    'product_translation.description_long'
];

$context = new Context(
    $associations,
    $inBasic,
    $prevent,
    $inject,
    $htmlFields,
    $virtualForeignKeys
);

$generator = new ApiGenerator(__DIR__ . '/../../src/Api/');

$generator->generate($context);