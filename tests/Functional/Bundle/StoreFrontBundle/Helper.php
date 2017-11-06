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

namespace Shopware\Tests\Functional\Bundle\StoreFrontBundle;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Bundle\ESIndexingBundle\Console\ProgressHelperInterface;
use Shopware\Bundle\StoreFrontBundle;
use Shopware\Bundle\StoreFrontBundle\Configurator\ConfiguratorGateway;
use Shopware\Bundle\StoreFrontBundle\Configurator\ProductConfigurationGateway;
use Shopware\Context\Struct\ShopContext;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\PaymentMethod\Struct\PaymentMethod;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\Shop\Struct\Shop;
use Shopware\Components\Api\Resource;
use Shopware\Kernel;
use Shopware\Models;
use Shopware\Models\Tax\Tax;

class Helper
{
    /**
     * @var Converter
     */
    protected $converter;
    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    private $db;

    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    private $entityManager;

    /**
     * @var \Shopware\Components\Api\Resource\Article
     */
    private $articleApi;

    /**
     * @var \Shopware\Components\Api\Resource\Translation
     */
    private $translationApi;

    /**
     * @var Resource\Variant
     */
    private $variantApi;

    /**
     * @var \Shopware\Components\Api\Resource\Category
     */
    private $categoryApi;

    private $createdProducts = [];
    private $createdManufacturers = [];
    private $createdCategories = [];
    private $createdCustomerGroups = [];
    private $createdTaxes = [];
    private $createdCurrencies = [];
    private $createdConfiguratorGroups = [];
    private $propertyNames = [];

    public function __construct()
    {
        $this->db = Shopware()->Db();
        $this->entityManager = Shopware()->Models();
        $this->converter = new Converter();

        $api = new Resource\Article();
        $api->setManager($this->entityManager);
        $this->articleApi = $api;

        $variantApi = new Resource\Variant();
        $variantApi->setManager($this->entityManager);
        $this->variantApi = $variantApi;

        $translation = new Resource\Translation();
        $translation->setManager($this->entityManager);
        $this->translationApi = $translation;

        $categoryApi = new Resource\Category();
        $categoryApi->setManager($this->entityManager);
        $this->categoryApi = $categoryApi;
    }

    public function getProductConfigurator(
        StoreFrontBundle\Product\ListProduct $listProduct,
        \Shopware\Context\Struct\ShopContext $context,
        array $selection = [],
        ProductConfigurationGateway $productConfigurationGateway = null,
        ConfiguratorGateway $configuratorGateway = null
    ) {
        if ($productConfigurationGateway == null) {
            $productConfigurationGateway = Shopware()->Container()->get('storefront.configurator.product_configuration_gateway');
        }
        if ($configuratorGateway == null) {
            $configuratorGateway = Shopware()->Container()->get('storefront.configurator.gateway');
        }

        $service = new StoreFrontBundle\Configurator\ConfiguratorService(
            $productConfigurationGateway,
            $configuratorGateway
        );

        return $service->getProductConfigurator($listProduct, $context, $selection);
    }

    /**
     * @param \Shopware\Bundle\StoreFrontBundle\Product\ListProduct             $product
     * @param \Shopware\Context\Struct\ShopContext             $context
     * @param \Shopware\Bundle\StoreFrontBundle\Property\ProductPropertyGateway $productPropertyGateway
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Property\PropertySet
     */
    public function getProductProperties(
        StoreFrontBundle\Product\ListProduct $product,
        \Shopware\Context\Struct\ShopContext $context,
        StoreFrontBundle\Property\ProductPropertyGateway $productPropertyGateway = null
    ) {
        if ($productPropertyGateway === null) {
            $productPropertyGateway = Shopware()->Container()->get('storefront.property.product_property_gateway');
        }
        $service = new StoreFrontBundle\Property\PropertyService($productPropertyGateway);

        $properties = $service->getList([$product], $context);

        return array_shift($properties);
    }

    /**
     * @param array                $numbers
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param array                $configs
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Product\ListProduct[]
     */
    public function getListProducts($numbers, ShopContext $context, array $configs = [])
    {
        $config = Shopware()->Container()->get('config');
        $originals = [];
        foreach ($configs as $key => $value) {
            $originals[$key] = $config->get($key);
            $config->offsetSet($key, $value);
        }

        $service = Shopware()->Container()->get('storefront.product.list_product_service');
        $result = $service->getList($numbers, $context);
        foreach ($originals as $key => $value) {
            $config->offsetSet($key, $value);
        }

        return $result;
    }

    /**
     * @param string                                                         $number
     * @param \Shopware\Context\Struct\ShopContext $context
     * @param array                                                          $configs
     *
     * @return \Shopware\Bundle\StoreFrontBundle\Product\ListProduct
     */
    public function getListProduct($number, ShopContext $context, array $configs = [])
    {
        return array_shift($this->getListProducts([$number], $context, $configs));
    }

    /**
     * Creates a simple product which contains all required
     * data for an quick product creation.
     *
     * @param $number
     * @param Tax                                                           $tax
     * @param \Shopware\CustomerGroup\Struct\CustomerGroup $customerGroup
     * @param float                                                         $priceOffset
     *
     * @return array
     */
    public function getSimpleProduct(
        $number,
        $tax, // Either Model/Tax or Struct/Tax
        CustomerGroup $customerGroup,
        $priceOffset = 0.00
    ) {
        if ($customerGroup instanceof Models\Customer\Group) {
            $struct = new CustomerGroup();
            $struct->setId($customerGroup->getId());
            $struct->setKey($customerGroup->getKey());
            $struct->setName($customerGroup->getName());
        }

        $data = $this->getProductData([
            'taxId' => $tax->getId(),
        ]);

        $data['mainDetail'] = $this->getVariantData([
            'number' => $number,
        ]);

        $data['mainDetail']['prices'] = $this->getGraduatedPrices(
            $customerGroup->getKey(),
            $priceOffset
        );

        $data['mainDetail'] += $this->getUnitData();

        return $data;
    }

    public function cleanUp()
    {
        foreach ($this->propertyNames as $name) {
            $this->deleteProperties($name);
        }
        $this->removePriceGroup();
        foreach ($this->createdProducts as $number) {
            $this->removeArticle($number);
        }

        foreach ($this->createdCustomerGroups as $key) {
            $this->deleteCustomerGroup($key);
        }

        foreach ($this->createdTaxes as $tax) {
            $this->deleteTax($tax);
        }

        foreach ($this->createdCurrencies as $currency) {
            $this->deleteCurrency($currency);
        }

        foreach ($this->createdCategories as $category) {
            try {
                $this->categoryApi->delete($category);
            } catch (\Exception $e) {
            }
        }

        foreach ($this->createdManufacturers as $manufacturerId) {
            try {
                $manufacturer = $this->entityManager->find('Shopware\Models\Article\Supplier', $manufacturerId);
                if (!$manufacturer) {
                    continue;
                }

                $this->entityManager->remove($manufacturer);
                $this->entityManager->flush();
            } catch (\Exception $e) {
            }
        }

        foreach ($this->createdConfiguratorGroups as $groupId) {
            $group = $this->entityManager->find('Shopware\Models\Article\Configurator\Group', $groupId);
            if (!$group) {
                continue;
            }
            $this->entityManager->remove($group);
            $this->entityManager->flush();
        }
    }

    /**
     * @param $number
     */
    public function removeArticle($number)
    {
        $articleId = $this->db->fetchOne(
            'SELECT articleID FROM s_articles_details WHERE ordernumber = ?',
            [$number]
        );

        if (!$articleId) {
            return;
        }

        $article = $this->entityManager->find('Shopware\Models\Article\Article', $articleId);

        if ($article) {
            $this->entityManager->remove($article);
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $detailIds = $this->db->fetchCol(
            'SELECT id FROM s_articles_details WHERE articleID = ?',
            [$articleId]
        );

        if (empty($detailIds)) {
            return;
        }

        foreach ($detailIds as $id) {
            $detail = $this->entityManager->find('Shopware\Models\Article\Detail', $id);
            if ($detail) {
                $this->entityManager->remove($detail);
                $this->entityManager->flush();
            }
        }
        $this->entityManager->clear();
    }

    /**
     * @param array $data
     *
     * @return Models\Article\Article
     */
    public function createArticle(array $data)
    {
        $this->removeArticle($data['mainDetail']['number']);
        $this->createdProducts[] = $data['mainDetail']['number'];

        return $this->articleApi->create($data);
    }

    public function createArticleTranslation($articleId, $shopId)
    {
        $data = [
            'type' => 'article',
            'key' => $articleId,
            'shopId' => $shopId,
            'data' => $this->getArticleTranslation(),
        ];
        $this->translationApi->create($data);
    }

    public function createManufacturerTranslation($manufacturerId, $shopId)
    {
        $data = [
            'type' => 'supplier',
            'key' => $manufacturerId,
            'shopId' => $shopId,
            'data' => $this->getManufacturerTranslation(),
        ];

        $this->translationApi->create($data);
    }

    public function createPropertyTranslation($properties, $shopId)
    {
        $this->translationApi->create([
            'type' => 'propertygroup',
            'key' => $properties['id'],
            'shopId' => $shopId,
            'data' => ['groupName' => 'Dummy Translation'],
        ]);

        foreach ($properties['groups'] as $group) {
            $this->translationApi->create([
                'type' => 'propertyoption',
                'key' => $group['id'],
                'shopId' => $shopId,
                'data' => ['optionName' => 'Dummy Translation group - ' . $group['id']],
            ]);

            foreach ($group['options'] as $option) {
                $this->translationApi->create([
                    'type' => 'propertyvalue',
                    'key' => $option['id'],
                    'shopId' => $shopId,
                    'data' => ['optionValue' => 'Dummy Translation option - ' . $group['id'] . ' - ' . $option['id']],
                ]);
            }
        }
    }

    public function createConfiguratorTranslation($configuratorSet, $shopId)
    {
        foreach ($configuratorSet['groups'] as $group) {
            $this->translationApi->create([
                'type' => 'configuratorgroup',
                'key' => $group['id'],
                'shopId' => $shopId,
                'data' => [
                    'name' => 'Dummy Translation group - ' . $group['id'],
                    'description' => 'Dummy Translation description - ' . $group['id'],
                ],
            ]);

            foreach ($group['options'] as $option) {
                $this->translationApi->create([
                    'type' => 'configuratoroption',
                    'key' => $option['id'],
                    'shopId' => $shopId,
                    'data' => [
                        'name' => 'Dummy Translation option - ' . $group['id'] . ' - ' . $option['id'],
                    ],
                ]);
            }
        }
    }

    public function createUnitTranslations(array $unitIds, $shopId, array $translation = [])
    {
        $data = [
            'type' => 'config_units',
            'key' => 1,
            'shopId' => $shopId,
            'data' => [],
        ];

        foreach ($unitIds as $id) {
            if (isset($translation[$id])) {
                $data['data'][$id] = array_merge(
                    $this->getUnitTranslation(),
                    $translation[$id]
                );
            } else {
                $data['data'][$id] = $this->getUnitTranslation();
            }
        }

        $this->translationApi->create($data);
    }

    public function createPriceGroup($discounts = [])
    {
        if (empty($discounts)) {
            $discounts = [
                ['key' => 'PHP', 'quantity' => 1,  'discount' => 10],
                ['key' => 'PHP', 'quantity' => 5,  'discount' => 20],
                ['key' => 'PHP', 'quantity' => 10, 'discount' => 30],
            ];
        }

        $this->removePriceGroup();

        $priceGroup = new Models\Price\Group();
        $priceGroup->setName('TEST-GROUP');

        $repo = $this->entityManager->getRepository('Shopware\Models\Customer\Group');
        $collection = [];
        foreach ($discounts as $data) {
            $discount = new Models\Price\Discount();
            $discount->setCustomerGroup(
                $repo->findOneBy(['key' => $data['key']])
            );

            $discount->setGroup($priceGroup);
            $discount->setStart($data['quantity']);
            $discount->setDiscount($data['discount']);

            $collection[] = $discount;
        }
        $priceGroup->setDiscounts($collection);

        $this->entityManager->persist($priceGroup);
        $this->entityManager->flush();
        $this->entityManager->clear();

        return $priceGroup;
    }

    public function createCustomerGroup($data = [])
    {
        $data = array_merge(
            [
                'key' => 'PHP',
                'name' => 'Unit test',
                'tax' => true,
                'taxInput' => true,
                'mode' => false, //use discounts?
                'discount' => 0, //percentage discount
            ],
            $data
        );

        $this->deleteCustomerGroup($data['key']);

        $customer = new Models\Customer\Group();
        $customer->fromArray($data);

        $this->entityManager->persist($customer);
        $this->entityManager->flush($customer);
        $this->entityManager->clear();

        $this->createdCustomerGroups[] = $customer->getKey();

        return $customer;
    }

    public function createTax($data = [])
    {
        $data = array_merge(
            [
                'tax' => 19.00,
                'name' => 'PHP UNIT',
            ],
            $data
        );

        $this->deleteTax($data['name']);

        $tax = new Models\Tax\Tax();
        $tax->fromArray($data);

        $this->entityManager->persist($tax);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->createdTaxes[] = $data['name'];

        return $tax;
    }

    public function createCurrency(array $data = [])
    {
        $currency = new Models\Shop\Currency();

        $data = array_merge(
            [
                'currency' => 'PHP',
                'factor' => 1,
                'name' => 'PHP',
                'default' => false,
                'symbol' => 'PHP',
            ],
            $data
        );

        $this->deleteCurrency($data['name']);

        $currency->fromArray($data);

        $this->entityManager->persist($currency);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->createdCurrencies[] = $data['name'];

        return $currency;
    }

    /**
     * @param array $data
     *
     * @return Models\Category\Category
     */
    public function createCategory(array $data = [])
    {
        $data = array_merge($this->getCategoryData(), $data);

        $this->deleteCategory($data['name']);

        $category = $this->categoryApi->create($data);

        $this->createdCategories[] = $category->getId();

        return $category;
    }

    /**
     * @param array $data
     *
     * @return Models\Article\Supplier
     */
    public function createManufacturer(array $data = [])
    {
        $data = array_merge($this->getManufacturerData(), $data);
        $manufacturer = new Models\Article\Supplier();
        $manufacturer->fromArray($data);
        $this->entityManager->persist($manufacturer);
        $this->entityManager->flush();

        $this->createdManufacturers[] = $manufacturer->getId();

        return $manufacturer;
    }

    public function createVotes($articleId, $points = [], $shopId = null)
    {
        $data = [
            'id' => null,
            'articleID' => $articleId,
            'name' => 'Bert Bewerter',
            'headline' => 'Super Artikel',
            'comment' => 'Dieser Artikel zeichnet sich durch extreme Stabilität aus und fasst super viele Klamotten. Das Preisleistungsverhältnis ist exorbitant gut.',
            'points' => null,
            'datum' => '2012-08-29 14:02:24',
            'active' => '1',
            'shop_id' => $shopId,
        ];

        foreach ($points as $point) {
            $data['points'] = $point;
            Shopware()->Db()->insert('s_articles_vote', $data);
        }
    }

    /**
     * @param \Shopware\CustomerGroup\Struct\CustomerGroup $customerGroup used for the price definition
     * @param string                                                        $number
     * @param array                                                         $data          contains nested configurator group > option array
     *
     * @return array
     */
    public function getConfigurator(
        CustomerGroup $customerGroup,
        $number,
        array $data = []
    ) {
        if (empty($data)) {
            $data = [
                'Farbe' => ['rot', 'gelb', 'blau'],
            ];
        }
        $groups = $this->insertConfiguratorData($data);

        $configurator = $this->createConfiguratorSet($groups);

        $variants = array_merge([
            'prices' => $this->getGraduatedPrices($customerGroup->getKey()),
        ], $this->getUnitData());

        $variants = $this->generateVariants(
            $configurator['groups'],
            $number,
            $variants
        );

        return [
            'configuratorSet' => $configurator,
            'variants' => $variants,
        ];
    }

    public function updateConfiguratorVariants($articleId, $data)
    {
        foreach ($data as $updateInformation) {
            $options = $updateInformation['options'];
            $variantData = $updateInformation['data'];

            $variants = $this->getVariantsByOptions($articleId, $options);

            if (empty($variants)) {
                continue;
            }

            foreach ($variants as $variantId) {
                $this->variantApi->update($variantId, $variantData);
            }
        }
    }

    public function getProductOptionsByName($articleId, $optionNames)
    {
        $query = $this->entityManager->getDBALQueryBuilder();
        $query->select(['options.id', 'options.group_id', 'options.name', 'options.position'])
            ->from('s_article_configurator_options', 'options')
            ->innerJoin(
                'options',
                's_article_configurator_option_relations',
                'relation',
                'relation.option_id = options.id'
            )
            ->innerJoin(
                'relation',
                's_articles_details',
                'variant',
                'variant.id = relation.article_id AND variant.articleID = :article'
            )
            ->groupBy('options.id')
            ->where('options.name IN (:names)')
            ->setParameter('article', $articleId)
            ->setParameter(':names', $optionNames, Connection::PARAM_STR_ARRAY);

        $statement = $query->execute();

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @param array $data
     *
     * @return array
     */
    public function getProductData(array $data = [])
    {
        return array_merge(
            [
                'id' => 277,
                'supplierId' => 1,
                'taxId' => 1,
                'name' => 'Unit test product',
                'description' => 'Lorem ipsum',
                'descriptionLong' => 'Lorem ipsum',
                'active' => true,
                'pseudoSales' => 2,
                'highlight' => true,
                'keywords' => 'Lorem',
                'metaTitle' => 'Lorem',
                'lastStock' => true,
                'crossBundleLook' => 0,
                'notification' => true,
                'template' => '',
                'mode' => 0,
                'availableFrom' => null,
                'availableTo' => null,
            ],
            $data
        );
    }

    public function getCategoryData()
    {
        return [
            'parent' => 3,
            'name' => 'Test-Category',
        ];
    }

    public function getManufacturerData()
    {
        return [
            'name' => 'Test-ProductManufacturer',
        ];
    }

    public function getVariantData(array $data = [])
    {
        return array_merge(
            [
                'number' => 'Variant-' . uniqid(rand()),
                'supplierNumber' => 'kn12lk3nkl213',
                'active' => 1,
                'inStock' => 222,
                'stockMin' => 51,
                'weight' => '2.000',
                'width' => '23.000',
                'len' => '32.000',
                'height' => '32.000',
                'ean' => '233',
                'position' => 0,
                'minPurchase' => 1,
                'shippingFree' => true,
                'releaseDate' => '2002-02-02 00:00:00',
                'shippingTime' => '2',
            ],
            $data
        );
    }

    public function getUnitData(array $data = [])
    {
        return array_merge(
            [
                'minPurchase' => 1,
                'purchaseSteps' => 2,
                'maxPurchase' => 100,
                'purchaseUnit' => 0.5000,
                'referenceUnit' => 1.000,
                'packUnit' => 'Flaschen',
                'unit' => [
                    'name' => 'Liter',
                ],
            ],
            $data
        );
    }

    public function getGraduatedPrices($group = 'EK', $priceOffset = 0)
    {
        return [
            [
                'from' => 1,
                'to' => 10,
                'price' => $priceOffset + 100.00,
                'customerGroupKey' => $group,
                'pseudoPrice' => $priceOffset + 110,
            ],
            [
                'from' => 11,
                'to' => 20,
                'price' => $priceOffset + 75.00,
                'customerGroupKey' => $group,
                'pseudoPrice' => $priceOffset + 85,
            ],
            [
                'from' => 21,
                'to' => 'beliebig',
                'price' => $priceOffset + 50.00,
                'customerGroupKey' => $group,
                'pseudoPrice' => $priceOffset + 60,
            ],
        ];
    }

    /**
     * @param string $image
     * @param array  $data
     *
     * @return array
     */
    public function getImageData(
        $image = 'test-spachtelmasse.jpg',
        array $data = []
    ) {
        return array_merge([
            'main' => 2,
            'link' => 'file://' . __DIR__ . '/fixtures/' . $image,
        ], $data);
    }

    /**
     * @param Models\Customer\Group $currentCustomerGroup
     * @param Models\Customer\Group $fallbackCustomerGroup
     * @param Models\Shop\Shop      $shop
     * @param array                 $taxes
     * @param Models\Shop\Currency  $currency
     *
     * @return TestContext
     */
    public function createContext(
        Models\Customer\Group $currentCustomerGroup,
        Models\Shop\Shop $shop,
        array $taxes,
        Models\Customer\Group $fallbackCustomerGroup = null,
        Models\Shop\Currency $currency = null
    ) {
        if ($currency == null && $shop->getCurrency()) {
            $currency = $this->converter->convertCurrency(
                $shop->getCurrency()
            );
        } else {
            $currency = $this->converter->convertCurrency($currency);
        }

        if ($fallbackCustomerGroup === null) {
            $fallbackCustomerGroup = $currentCustomerGroup;
        }

        $shop = $this->converter->convertShop($shop);

        return new TestContext(
            $shop,
            $currency,
            $this->converter->convertCustomerGroup($currentCustomerGroup),
            $this->converter->convertCustomerGroup($fallbackCustomerGroup),
            $this->buildTaxRules($taxes),
            [],
            new PaymentMethod(1, 'cash', 'Cash', 'Cash'),
            new ShippingMethod(1, 'prime', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
            ShippingLocation::createFromCountry($shop->getCountry()),
            null
        );
    }

    public function getProperties($groupCount, $optionCount, $namePrefix = 'Test')
    {
        $properties = $this->createProperties($groupCount, $optionCount, $namePrefix);
        $options = [];
        foreach ($properties['groups'] as $group) {
            $options = array_merge($options, $group['options']);
        }

        return [
            'filterGroupId' => $properties['id'],
            'propertyValues' => $options,
            'all' => $properties,
        ];
    }

    /**
     * @param int $shopId
     *
     * @return \Shopware\Models\Shop\Shop
     */
    public function getShop($shopId = 1)
    {
        return $this->entityManager->find(
            'Shopware\Models\Shop\Shop',
            $shopId
        );
    }

    /**
     * @return bool
     */
    public function isElasticSearchEnabled()
    {
        /** @var Kernel $kernel */
        $kernel = Shopware()->Container()->get('kernel');

        return $kernel->isElasticSearchEnabled();
    }

    /**
     * @param \Shopware\Shop\Struct\Shop $shop
     */
    public function refreshSearchIndexes(Shop $shop)
    {
        if (!$this->isElasticSearchEnabled()) {
            return;
        }

        $factory = Shopware()->Container()->get('shopware_elastic_search.index_factory');
        $index = $factory->createShopIndex($shop);

        try {
            $client = Shopware()->Container()->get('shopware_elastic_search.client');
            $client->indices()->delete(['index' => $index->getName()]);
        } catch (\Exception $e) {
        }

        $indexer = Shopware()->Container()->get('shopware_elastic_search.shop_indexer');
        $indexer->index($shop, new ProgressHelper());
    }

    private function createProperties($groupCount, $optionCount, $namePrefix = 'Test')
    {
        $this->propertyNames[] = $namePrefix;

        $this->deleteProperties($namePrefix);

        $this->db->insert('s_filter', ['name' => $namePrefix . '-PropertySet', 'comparable' => 1]);
        $data = $this->db->fetchRow("SELECT * FROM s_filter WHERE name = '" . $namePrefix . "-PropertySet'");

        for ($i = 0; $i < $groupCount; ++$i) {
            $this->db->insert('s_filter_options', [
                'name' => $namePrefix . '-Gruppe-' . $i,
                'filterable' => 1,
            ]);
            $group = $this->db->fetchRow("SELECT * FROM s_filter_options WHERE name = '" . $namePrefix . '-Gruppe-' . $i . "'");

            for ($i2 = 0; $i2 < $optionCount; ++$i2) {
                $this->db->insert('s_filter_values', [
                    'value' => $namePrefix . '-PropertyOption-' . $i . '-' . $i2,
                    'optionID' => $group['id'],
                ]);
            }

            $group['options'] = $this->db->fetchAll('SELECT * FROM s_filter_values WHERE optionID = ?', [$group['id']]);

            $data['groups'][] = $group;

            $this->db->insert('s_filter_relations', [
                'optionID' => $group['id'],
                'groupID' => $data['id'],
            ]);
        }

        return $data;
    }

    private function deleteProperties($namePrefix = 'Test')
    {
        $this->db->query("DELETE FROM s_filter WHERE name = '" . $namePrefix . "-PropertySet'");

        $ids = $this->db->fetchCol("SELECT id FROM s_filter_options WHERE name LIKE '" . $namePrefix . "-Gruppe%'");
        foreach ($ids as $id) {
            $this->db->query('DELETE FROM s_filter_options WHERE id = ?', [$id]);
            $this->db->query('DELETE FROM s_filter_relations WHERE optionID = ?', [$id]);
        }

        $this->db->query("DELETE FROM s_filter_values WHERE value LIKE '" . $namePrefix . "-PropertyOption%'");
    }

    private function deleteCategory($name)
    {
        $ids = Shopware()->Db()->fetchCol('SELECT id FROM s_categories WHERE description = ?', [$name]);

        foreach ($ids as $id) {
            $this->categoryApi->delete($id);
        }
    }

    private function getVariantsByOptions($articleId, $options)
    {
        $ids = $this->getProductOptionsByName($articleId, $options);
        $ids = array_column($ids, 'id');

        $query = $this->entityManager->getDBALQueryBuilder();
        $query->select([
            'relation.article_id',
            "CONCAT('|', GROUP_CONCAT(relation.option_id SEPARATOR '|'), '|') as options",
        ]);

        $query->from('s_article_configurator_option_relations', 'relation')
            ->innerJoin('relation', 's_articles_details', 'variant', 'variant.id = relation.article_id')
            ->where('variant.articleID = :article')
            ->groupBy('relation.article_id')
            ->setParameter(':article', $articleId);

        foreach ($ids as $id) {
            $query->andHaving("options LIKE '%|" . (int) $id . "|%'");
        }

        $statement = $query->execute();
        $ids = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($ids, 'article_id');
    }

    /**
     * @param Models\Article\Configurator\Group[] $groups
     *
     * @return array
     */
    private function createConfiguratorSet(array $groups)
    {
        $data = [];

        foreach ($groups as $group) {
            $options = [];
            /** @var $option Models\Article\Configurator\Option */
            foreach ($group->getOptions() as $option) {
                $options[] = [
                    'id' => $option->getId(),
                    'name' => $option->getName(),
                ];
            }
            $data[] = [
                'id' => $group->getId(),
                'name' => $group->getName(),
                'options' => $options,
            ];
        }

        return [
            'name' => 'Unit test configurator set',
            'groups' => $data,
        ];
    }

    private function insertConfiguratorData($groups)
    {
        $pos = 1;
        $data = [];

        foreach ($groups as $groupName => $options) {
            $group = new Models\Article\Configurator\Group();
            $group->setName($groupName);
            $group->setPosition($groups);
            $this->db->executeQuery('DELETE FROM s_article_configurator_groups WHERE name = ?', [$groupName]);

            $collection = [];
            $optionPos = 1;
            foreach ($options as $optionName) {
                $this->db->executeQuery('DELETE FROM s_article_configurator_options WHERE name = ?', [$optionName]);

                $option = new Models\Article\Configurator\Option();
                $option->setName($optionName);
                $option->setPosition($optionPos);
                $collection[] = $option;
                ++$optionPos;
            }
            $group->setOptions($collection);
            ++$pos;

            $data[] = $group;

            $this->entityManager->persist($group);
            $this->entityManager->flush();
            $this->entityManager->clear();

            $this->createdConfiguratorGroups[] = $group->getId();
        }

        return $data;
    }

    private function deleteCustomerGroup($key)
    {
        $ids = $this->db->fetchCol('SELECT id FROM s_core_customergroups WHERE groupkey = ?', [$key]);
        if (!$ids) {
            return;
        }

        foreach ($ids as $id) {
            $customer = $this->entityManager->find('Shopware\Models\Customer\Group', $id);
            if (!$customer) {
                continue;
            }
            $this->entityManager->remove($customer);
            $this->entityManager->flush($customer);
        }
        $this->entityManager->clear();
    }

    private function deleteTax($name)
    {
        $ids = $this->db->fetchCol('SELECT id FROM s_core_tax WHERE description = ?', [$name]);
        if (empty($ids)) {
            return;
        }

        foreach ($ids as $id) {
            $tax = $this->entityManager->find('Shopware\Models\Tax\Tax', $id);
            $this->entityManager->remove($tax);
            $this->entityManager->flush();
        }
        $this->entityManager->clear();
    }

    private function deleteCurrency($name)
    {
        $ids = $this->db->fetchCol('SELECT id FROM s_core_currencies WHERE name = ?', [$name]);
        if (empty($ids)) {
            return;
        }

        foreach ($ids as $id) {
            $tax = $this->entityManager->find('Shopware\Models\Shop\Currency', $id);
            $this->entityManager->remove($tax);
            $this->entityManager->flush();
        }
        $this->entityManager->clear();
    }

    private function removePriceGroup()
    {
        $ids = $this->db->fetchCol("SELECT id FROM s_core_pricegroups WHERE description = 'TEST-GROUP'");
        foreach ($ids as $id) {
            $group = $this->entityManager->find('Shopware\Models\Price\Group', $id);
            $this->entityManager->remove($group);
            $this->entityManager->flush();
            $this->entityManager->clear();
        }
    }

    /**
     * Helper function which combines all array elements
     * of the passed arrays.
     *
     * @param $arrays
     * @param int $i
     *
     * @return array
     */
    private function combinations($arrays, $i = 0)
    {
        if (!isset($arrays[$i])) {
            return [];
        }
        if ($i == count($arrays) - 1) {
            return $arrays[$i];
        }

        $tmp = $this->combinations($arrays, $i + 1);
        $result = [];

        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) {
                $result[] = is_array($t) ? array_merge([$v], $t) : [$v, $t];
            }
        }

        return $result;
    }

    /**
     * Helper function which creates all variants for
     * the passed groups with options.
     *
     * @param $groups
     * @param null  $numberPrefix
     * @param array $data
     *
     * @return array
     */
    private function generateVariants(
        $groups,
        $numberPrefix = null,
        $data = []
    ) {
        $options = [];

        foreach ($groups as $group) {
            $groupOptions = [];
            foreach ($group['options'] as $option) {
                $groupOptions[] = [
                    'groupId' => $group['id'],
                    'option' => $option['name'],
                ];
            }
            $options[] = $groupOptions;
        }

        $combinations = $this->combinations($options);
        $combinations = $this->cleanUpCombinations($combinations);

        $variants = [];
        $count = 1;
        if (!$numberPrefix) {
            $numberPrefix = 'Unit-Test-Variant-';
        }

        $this->db->executeQuery(
            'DELETE FROM s_articles_details WHERE ordernumber LIKE ?',
            [$numberPrefix . '%']
        );

        foreach ($combinations as $combination) {
            $variantData = array_merge(
                ['number' => $numberPrefix . $count],
                $data
            );

            $variant = $this->getVariantData($variantData);

            $variant['configuratorOptions'] = $combination;
            $variants[] = $variant;

            ++$count;
        }

        return $variants;
    }

    /**
     * Combinations merge the result of dimensional arrays not perfectly
     * so we have to clean up the first array level.
     *
     * @param $combinations
     *
     * @return mixed
     */
    private function cleanUpCombinations($combinations)
    {
        foreach ($combinations as &$combination) {
            $combination[] = ['option' => $combination['option'], 'groupId' => $combination['groupId']];
            unset($combination['groupId']);
            unset($combination['option']);
        }

        return $combinations;
    }

    /**
     * @param Models\Tax\Tax[] $taxes
     *
     * @return \Shopware\Tax\Struct\Tax[]
     */
    private function buildTaxRules(array $taxes)
    {
        $rules = [];
        foreach ($taxes as $model) {
            $key = 'tax_' . $model->getId();
            $struct = $this->converter->convertTax($model);
            $rules[$key] = $struct;
        }

        return $rules;
    }

    private function getUnitTranslation()
    {
        return [
            'unit' => 'Dummy Translation',
            'description' => 'Dummy Translation',
        ];
    }

    private function getManufacturerTranslation()
    {
        return [
            'metaTitle' => 'Dummy Translation',
            'description' => 'Dummy Translation',
            'metaDescription' => 'Dummy Translation',
            'metaKeywords' => 'Dummy Translation',
        ];
    }

    private function getArticleTranslation()
    {
        return [
            'txtArtikel' => 'Dummy Translation',
            'txtshortdescription' => 'Dummy Translation',
            'txtlangbeschreibung' => 'Dummy Translation',
            'txtzusatztxt' => 'Dummy Translation',
            'txtkeywords' => 'Dummy Translation',
            'txtpackunit' => 'Dummy Translation',
            'metaTitle' => 'Dummy Translation',
        ];
    }
}

class ProgressHelper implements ProgressHelperInterface
{
    public function start(int $count, string $label = ''): void
    {
    }

    public function advance(int $step = 1): void
    {
    }

    public function finish(): void
    {
    }

    public function setMessage(string $message, string $name = 'message'): void
    {
    }

    public function writeln(string $messages, int $options = 0): void
    {
    }

    public function setFormat(string $format): void
    {
    }
}
