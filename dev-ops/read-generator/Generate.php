<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
(new \Symfony\Component\Dotenv\Dotenv())->load(__DIR__.'/../../.env');
require_once __DIR__ . '/../../src/Framework/Doctrine/DatabaseConnector.php';
require_once __DIR__ . '/DomainGenerator.php';

class Generate
{
    const ManyToOne = 'N:1';
    const ManyToMany = 'N:N';
    const OneToMany = '1:N';

    public function execute()
    {
        $connection = \Shopware\Framework\Doctrine\DatabaseConnector::createPdoConnection();

        $dbalConnection = new \Doctrine\DBAL\Connection(
            ['pdo' => $connection],
            new Doctrine\DBAL\Driver\PDOMySql\Driver(),
            null,
            null
        );

        $dir = __DIR__ . '/../../output';

        $generator = new DomainGenerator($dbalConnection, $dir);

        $tables = [
            'product' => [
                'seo_url_name' => 'detail_page',
                'associations' => [
                    self::createAssociation('product_manufacturer', self::ManyToOne, true, false, 'manufacturer', 'product_manufacturer_uuid', '', '', false),
                    self::createAssociation('product_detail', self::ManyToOne, true, false, 'mainDetail', 'main_detail_uuid', '', '', false),
                    self::createAssociation('tax', self::ManyToOne, true, false, 'tax', 'tax_uuid', '', '', false),
                    self::createAssociation('seo_url', self::ManyToOne, true, false, 'canonicalUrl', ''),
                    self::createAssociation('price_group', self::ManyToOne, true, false, 'priceGroup', 'price_group_uuid'),
                    self::createAssociation('customer_group', self::ManyToMany, true, true, 'blockedCustomerGroups', '', 'product_avoid_customer_group'),
                    self::createAssociation('product_detail', self::OneToMany, false, true, 'detail', 'product_uuid', '', '', true, true),
                    self::createAssociation('category', self::ManyToMany, false, true, 'category', 'product_uuid', 'product_category_ro'),
                    self::createAssociation('product_vote', self::OneToMany, false, true, 'vote', 'product_uuid'),
//                    self::createAssociation('shop', self::ManyToMany, false, true, 'shop', '', 'shop_currency'),
                ]
            ],
            'product_vote' => [
                self::createAssociation('shop', self::ManyToOne, true, false, 'shop', 'shop_uuid'),
            ],
            'seo_url' => [
                'collection_functions' => [
                    file_get_contents(__DIR__ . '/special_case/seo_url/collection_functions.txt')
                ],
                'columns' => [
                    'seo_hash' => [
                        'functions' => file_get_contents(__DIR__ . '/special_case/seo_url/seo_hash.txt')
                    ],
                ]
            ],
            'tax' => [],
            'product_price' => [
                'associatons' => [
                    self::createAssociation('customer_group', self::ManyToOne, true, false, 'customerGroup', 'customer_group_uuid', '', '', false)
                ]
            ],
            'product_detail' => [
                'associations' => [
                    self::createAssociation('unit', self::ManyToOne, true, false, 'unit', 'unit_uuid'),
                    self::createAssociation('product_price', self::OneToMany, false, true, 'price', 'product_detail_uuid'),
                ],
            ],
            'product_manufacturer' => [],
            'shop' => [
                'columns' => [
                    'base_url' => [
                        'functions' => file_get_contents(__DIR__ . '/special_case/shop/base_url_functions.txt')
                    ],
                    'base_path' => [
                        'functions' => file_get_contents(__DIR__ . '/special_case/shop/base_path_functions.txt')
                    ]
                ],
                'associations' => [
                    self::createAssociation('currency', self::ManyToOne, true, false, 'currency', 'currency_uuid', '', '', false),
                    self::createAssociation('locale', self::ManyToOne, true, false, 'locale', 'locale_uuid', '', '', false),

                    self::createAssociation('locale', self::ManyToOne, false, false, 'fallbackLocale', 'fallback_locale_uuid'),
                    self::createAssociation('category', self::ManyToOne, false, false, 'category', 'category_uuid', '', '', false),

                    self::createAssociation('customer_group', self::ManyToOne, false, false, 'customerGroup', 'customer_group_uuid', '', '', false),
                    self::createAssociation('payment_method', self::ManyToOne, false, false, 'paymentMethod', 'payment_method_uuid', '', '', false),
                    self::createAssociation('shipping_method', self::ManyToOne, false, false, 'shippingMethod', 'shipping_method_uuid', '', '', false),
                    self::createAssociation('area_country', self::ManyToOne, false, false, 'country', 'area_country_uuid', '', '', false),

                    self::createAssociation('shop_template', self::ManyToOne, false, false, 'template', 'shop_template_uuid', '', '', false),
                    self::createAssociation('currency', self::ManyToMany, false, true, 'availableCurrency', 'currency_uuid', 'shop_currency', ''),
                ],
                'collection_functions' => [
                    file_get_contents(__DIR__ . '/special_case/shop/collection_functions.txt')
                ]
            ],
            'payment_method' => [
                'associations' => [
                    self::createAssociation('shop', self::ManyToMany, false, true, 'shop', '', 'payment_method_shop'),
                    self::createAssociation('area_country', self::ManyToMany, false, true, 'country', '', 'payment_method_country')
                ]
            ],
            'shipping_method' => [
                'associations' => [
                    self::createAssociation('category', self::ManyToMany, false, true, 'category', '', 'shipping_method_category'),
                    self::createAssociation('area_country', self::ManyToMany, false, true, 'country', '', 'shipping_method_country'),
                    self::createAssociation('holiday', self::ManyToMany, false, true, 'holiday', '', 'shipping_method_holiday'),
                    self::createAssociation('payment_method', self::ManyToMany, false, true, 'paymentMethod', '', 'shipping_method_payment_method'),
                    self::createAssociation('shipping_method_price', self::OneToMany, false, true, 'price', 'shipping_method_uuid'),
                ]
            ],
            'shipping_method_price' => [],
            'currency' => [
                'associations' => [
                    self::createAssociation('shop', self::ManyToMany, false, true, 'shop', '', 'shop_currency'),
                ],
                'collection_functions' => [
                    file_get_contents(__DIR__ . '/special_case/currency/collection_functions.txt')
                ]
            ],
            'media' => [
                'associations' => [
                    self::createAssociation('album', self::ManyToOne, true, false, 'album', 'album_uuid'),
                ]
            ],
            'category' => [
                'seo_url_name' => 'listing_page',
                'columns' => [
                    'path' => ['type' => 'array'],
                    'facet_ids' => ['type' => 'array'],
                    'sorting_ids' => ['type' => 'array'],
                ],
                'associations' => [
                    self::createAssociation('product_stream', self::ManyToOne, false, false, 'productStream', 'product_stream_uuid'),
                    self::createAssociation('media', self::ManyToOne, false, false, 'media', 'media_uuid'),
                    self::createAssociation('seo_url', self::ManyToOne, true, false, 'canonicalUrl', ''),
                    self::createAssociation('product', self::ManyToMany, false, true, 'product', '', 'product_category_ro'),
                    self::createAssociation('customer_group', self::ManyToMany, false, true, 'blockedCustomerGroups', '', 'category_avoid_customer_group'),
                ],
                'struct_functions' => [
                    file_get_contents(__DIR__ . '/special_case/category/struct_children.txt')
                ],
                'collection_functions' => [
                    file_get_contents(__DIR__ . '/special_case/category/collection_build_tree.txt')
                ]
            ],
            'customer' => [
                'associations' => [
                    self::createAssociation('customer_group', self::ManyToOne, true, false, 'customerGroup', 'customer_group_uuid', '', '', false),
                    self::createAssociation('customer_address', self::ManyToOne, true, false, 'defaultShippingAddress', 'default_shipping_address_uuid', '', '', false),
                    self::createAssociation('customer_address', self::ManyToOne, true, false, 'defaultBillingAddress', 'default_billing_address_uuid', '', '', false),
                    self::createAssociation('payment_method', self::ManyToOne, true, false, 'lastPaymentMethod', 'last_payment_method_uuid'),
                    self::createAssociation('payment_method', self::ManyToOne, true, false, 'defaultPaymentMethod', 'default_payment_method_uuid', '', '', false),
                    self::createAssociation('customer_address', self::OneToMany, false, true, 'address', 'customer_uuid'),
                    self::createAssociation('shop', self::ManyToOne, false, false, 'shop', 'shop_uuid', '', '', false),
                ],
                'struct_functions' => [
                    file_get_contents(__DIR__ . '/special_case/customer/active_addresses.txt')
                ]
            ],
            'customer_address' => [
                'associations' => [
                    self::createAssociation('area_country', self::ManyToOne, true, false, 'country', 'area_country_uuid', '', '', false),
                    self::createAssociation('area_country_state', self::ManyToOne, true, false, 'state', 'area_country_state_uuid', '', '', true),
                ]
            ],
            'product_stream' => [
                'associations' => [
                    self::createAssociation('listing_sorting', self::ManyToOne, true, false, 'sorting', 'listing_sorting_uuid', '', '', false)
                ]
            ],
            'album' => [
                'associations' => [
                    self::createAssociation('media', self::OneToMany, false, true, 'media', 'album_uuid')
                ]
            ],
            'area' => [
                'associations' => [
                    self::createAssociation('area_country', self::OneToMany, false, true, 'country', 'area_uuid', '', '', true, true)
                ]
            ],
            'area_country' => [
                'associations' => [
                    self::createAssociation('area_country_state', self::OneToMany, false, true, 'state', 'area_country_uuid')
                ]
            ],
            'area_country_state' => [
            ],
            'customer_group' => [
                'associations' => [
                    self::createAssociation('customer_group_discount', self::OneToMany, false, true, 'discount', 'customer_group_uuid')
                ]
            ],
            'customer_group_discount' => [
            ],
            'holiday' => [],
            'locale' => [],
            'price_group' => [
                'associations' => [
                    self::createAssociation('price_group_discount', self::OneToMany, false, true, 'discount', 'price_group_uuid')
                ]
            ],
            'price_group_discount' => [
            ],
            'shop_template' => [],
            'tax_area_rule' => [],
            'unit' => [],
            'listing_sorting' => []
        ];

        foreach ($tables as $table => $assocs) {
            $generator->generate($table, $assocs);
        }

    }

    /**
     * @param $dir
     */
    protected function deleteDirectory($dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        rmdir($dir);
    }

    private static function createAssociation(string $table, string $type, bool $inBasic, bool $loadByLoader, string $property, string $foreignKeyColumn, string $mappingTable = '', string $condition = '', $nullable = true, $hasDetailLoader = false)
    {
        return [
            'in_basic' => $inBasic,                        //defines if it should be added to basic struct
            'load_by_association_loader' => $loadByLoader,      //true to fetch directly in query, false to fetch over associated basic loader
            'type' => $type,
            'table' => $table,
            'mapping' => $mappingTable,
            'condition' => $condition,
            'property' => $property,
            'foreignKeyColumn' => $foreignKeyColumn,
            'nullable' => $nullable,
            'has_detail_loader' => $hasDetailLoader
        ];
    }
}

$command = new Generate();
$command->execute();