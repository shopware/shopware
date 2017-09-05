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
require_once __DIR__ . '/StructGenerator.php';

class GenerateStructCommand
{
    const ManyToOne = 'N:1';
    const ManyToMany = 'N:N';
    const OneToMany = '1:N';

    /**
     * @return array
     */
    private static function createSearchCriteria(string $column, string $type, bool $sorting = true, bool $condition = true, bool $facet = true, string $className = ''): array
    {
        return ['column' => $column, 'type' => $type, 'sorting' => $sorting,'condition' => $condition,'facet' => $facet, 'className' => $className];
    }

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

        $generator = new StructGenerator($dbalConnection, $dir);

        if (file_exists($dir)) {
            $this->deleteDirectory($dir);
        }

        $tables = [
            'product_translation' => [],
            'product' => [
                'create_detail' => true,
                'parent' => null,
                'columns' => [
                ],
                'associations' => [
                    self::createAssociation('product_manufacturer', self::ManyToOne, true, false, '', '', 'manufacturer'),
                    self::createAssociation('product_detail', self::ManyToOne, true, false, '', 'product.main_detail_uuid = productDetail.uuid', 'mainDetail', 'main_detail_uuid'),
                    self::createAssociation('tax', self::ManyToOne, true, false),
                    self::createAssociation('seo_url', self::ManyToOne, true, false, '', 'product.uuid = seoUrl.foreign_key AND seoUrl.is_canonical = 1 AND seoUrl.shop_uuid = :shopUuid AND seoUrl.name = :seoUrlName', 'canonicalUrl'),
                    self::createAssociation('product_detail', self::OneToMany, false, true, '', '', 'detail'),
                    self::createAssociation('category', self::ManyToMany, false, false, 'product_category', '', ''),
                ],
                'search' => [
//                    self::createSearchCriteria('tax_uuid', 'string_array'),
//                    self::createSearchCriteria('main_detail_uuid', 'string_array', true, true, true, 'product_detail_uuid'),
//                    self::createSearchCriteria('product_manufacturer_uuid', 'string_array'),
//                    self::createSearchCriteria('topseller', 'boolean'),
                    self::createSearchCriteria('active', 'boolean'),
//                    self::createSearchCriteria('last_stock', 'boolean'),
//                    self::createSearchCriteria('notification', 'boolean'),
                ]
            ],
            'seo_url' => [
                'search' => [
//                    self::createSearchCriteria('seo_hash', 'string_array'),
                    self::createSearchCriteria('shop_uuid', 'string_array'),
                    self::createSearchCriteria('name', 'string_array'),
                    self::createSearchCriteria('foreign_key', 'string_array'),
                    self::createSearchCriteria('path_info', 'string_array'),
                    self::createSearchCriteria('seo_path_info', 'string_array'),
                    self::createSearchCriteria('is_canonical', 'boolean'),
                ]
            ],
            'tax' => [
                'search' => [
                ]
            ],

            'product_price' => [
                'search' => [
                    self::createSearchCriteria('product_detail_uuid', 'string_array'),
                ]
            ],
            'product_detail' => [
                'create_detail' => true,
                'associations' => [
                    self::createAssociation('unit', self::ManyToOne, true, false),
                    self::createAssociation('product_price', self::OneToMany, false, true, '', '', 'price'),
                ],
                'search' => [
                    self::createSearchCriteria('product_uuid', 'string_array'),
//                    self::createSearchCriteria('order_number', 'string_array'),
//                    self::createSearchCriteria('supplier_number', 'string_array'),
//                    self::createSearchCriteria('product_uuid', 'string_array'),
//                    self::createSearchCriteria('product_uuid', 'string_array'),
//                    self::createSearchCriteria('active', 'boolean'),
//                    self::createSearchCriteria('is_main', 'boolean'),
                ]
            ],
            'product_manufacturer' => [

            ],
            'shop' => [
                'create_detail' => true,
                'parent' => null,
                'columns' => [],
                'associations' => [
                    self::createAssociation('category', self::ManyToOne, false, false),
                    self::createAssociation('currency', self::ManyToOne, true, false),
                    self::createAssociation('locale', self::ManyToOne, true, false),
                    self::createAssociation('locale', self::ManyToOne, false, true, '', '', 'fallbackLocale', 'fallback_locale_uuid'),
                    self::createAssociation('shipping_method', self::ManyToOne, false, false),
                    self::createAssociation('shop_template', self::ManyToOne, false, false),
                    self::createAssociation('area_country', self::ManyToOne, false, false),
                    self::createAssociation('payment_method', self::ManyToOne, false, false),
                    self::createAssociation('customer_group', self::ManyToOne, false, false),
                    self::createAssociation('currency', self::ManyToMany, false, true, 'shop_currency')
                ],
                'search' => [
                    self::createSearchCriteria('parent_uuid', 'string_array'),
                    self::createSearchCriteria('category_uuid', 'string_array'),
                    self::createSearchCriteria('active', 'boolean'),
                ]
            ],
            'payment_method' => [
                'associations' => [
                    self::createAssociation('shop', self::ManyToMany, false, true, 'payment_method_shop'),
                    self::createAssociation('area_country', self::ManyToMany, false, true, 'payment_method_country')
                ]
            ],
            'shipping_method' => [
                'create_detail' => true,
                'parent' => null,
                'columns' => [],
                'associations' => [
                    self::createAssociation('shipping_method_price', self::OneToMany, false, true),
                    self::createAssociation('area_country', self::ManyToMany, false, true, 'shipping_method_country'),
                    self::createAssociation('category', self::ManyToMany, false, true, 'shipping_method_category'),
                    self::createAssociation('holiday', self::ManyToMany, false, true, 'shipping_method_holiday'),
                    self::createAssociation('payment_method', self::ManyToMany, false, true, 'shipping_method_payment_method'),
                ]
            ],
            'shipping_method_price' => [
                'parent' => 'shipping_method',
                'search' => [
                    self::createSearchCriteria('shipping_method_uuid', 'string_array'),
                ]
            ],
            'currency' => [],
            'media' => [],
            'category' => [
                'create_detail' => false,
                'parent' => null,
                'columns' => [
                    'path' => ['type' => 'array'],
                    'facet_ids' => ['type' => 'array'],
                    'sorting_ids' => ['type' => 'array'],
                ],
                'associations' => [
                    self::createAssociation('product_stream', self::ManyToOne, false, false),
                    self::createAssociation('media', self::ManyToOne, false, false),
                    self::createAssociation('seo_url', self::ManyToOne, true, false, '', 'category.uuid = seoUrl.foreign_key AND seoUrl.is_canonical = 1 AND seoUrl.shop_uuid = :shopUuid AND seoUrl.name = :seoUrlName', 'canonicalUrl'),
                ],
                'search' => [
                    self::createSearchCriteria('parent_uuid', 'string_array'),
                    self::createSearchCriteria('active', 'boolean'),
//                    self::createSearchCriteria('customer_group_uuid', 'string_array')
                ]
            ],
            'customer' => [
                'create_detail' => true,
                'parent' => null,
                'associations' => [
                    self::createAssociation('customer_group', self::ManyToOne, true, false),
                    self::createAssociation('customer_address', self::ManyToOne, true, true, '', '', 'defaultShippingAddress', 'default_shipping_address_uuid'),
                    self::createAssociation('customer_address', self::ManyToOne, true, true, '', '', 'defaultBillingAddress', 'default_billing_address_uuid'),
                    self::createAssociation('payment_method', self::ManyToOne, true, true, '', '', 'lastPaymentMethod', 'last_payment_method_uuid'),
                    self::createAssociation('payment_method', self::ManyToOne, true, true, '', '', 'defaultPaymentMethod', 'default_payment_method_uuid'),

                    //grouped fetch! @todo@dr please implement fetches with multiple keys in one table (default_billing_address, default_shipping_address)
                    self::createAssociation('customer_address', self::OneToMany, false, true),
                    self::createAssociation('shop', self::ManyToOne, false, false),
                ],
                'search' => [
//                    self::createSearchCriteria('active', 'boolean'),
                    self::createSearchCriteria('default_payment_method_uuid', 'string_array', true, true, true, 'PaymentMethodUuid'),

//                    self::createSearchCriteria('account_mode', 'int_array'),
                ]
            ],
            'customer_address' => [
                'create_detail' => false,
                'parent' => 'customer',
                'associations' => [
                    self::createAssociation('area_country', self::ManyToOne, true, false),
                    self::createAssociation('area_country_state', self::ManyToOne, true, false),
                ],
                'search' => [
                    self::createSearchCriteria('area_country_uuid', 'string_array'),
                    self::createSearchCriteria('area_country_state_uuid', 'string_array'),
                    self::createSearchCriteria('customer_uuid', 'string_array'),
                ]
            ],
            'product_stream' => [
                'create_detail' => false,
                'parent' => null,
                'associations' => [
                    self::createAssociation('listing_sorting', self::ManyToOne, true, false)
                ]
            ],
            'album' => [
                'associations' => [
                    self::createAssociation('media', self::OneToMany, false, true)
                ]
            ],
            'area' => [
                'create_detail' => true,
                'parent' => null,
                'associations' => [
                    self::createAssociation('area_country', self::OneToMany, false, true)
                ]
            ],
            'area_country' => [
                'create_detail' => true,
                'parent' => 'area',
                'associations' => [
                    self::createAssociation('area_country_state', self::OneToMany, false, true)
                ],
                'search' => [
                    self::createSearchCriteria('area_uuid', 'string_array'),
                ]
            ],
            'area_country_state' => [
                'create_detail' => false,
                'parent' => 'area_country',
                'associations' => []
            ],
            'customer_group' => [
                'create_detail' => true,
                'parent' => null,
                'associations' => [
                    self::createAssociation('customer_group_discount', self::OneToMany, false, true)
                ],
                'search' => [
                    self::createSearchCriteria('group_key', 'string_array')
                ]
            ],
            'customer_group_discount' => [
                'search' => [
                    self::createSearchCriteria('customer_group_uuid', 'string_array'),
                ]
            ],
            'holiday' => [],
            'locale' => [],
            'price_group' => [
                'create_detail' => true,
                'parent' => null,
                'associations' => [
                    self::createAssociation('price_group_discount', self::OneToMany, false, true)
                ]
            ],
            'price_group_discount' => [
                'create_detail' => false,
                'parent' => null,
                'associations' => [],
                'search' => [
                    self::createSearchCriteria('price_group_uuid', 'string_array'),
                ]
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

    private static function createAssociation(string $table, string $type, bool $inBasic, bool $loadByLoader, string $mappingTable = '', string $condition = '', string $property = '', string $foreignKeyColumn = '')
    {
        return [
            'in_basic' => $inBasic,                        //defines if it should be added to basic struct
            'load_by_association_loader' => $loadByLoader,      //true to fetch directly in query, false to fetch over associated basic loader
            'type' => $type,
            'table' => $table,
            'mapping' => $mappingTable,
            'condition' => $condition,
            'property' => $property,
            'foreignKeyColumn' => $foreignKeyColumn
        ];
    }
}

$command = new GenerateStructCommand();
$command->execute();