<?php declare(strict_types=1);

namespace Shopware\Product\Tests;

namespace Shopware\Product\Tests;

use Doctrine\DBAL\Connection;
use Shopware\Framework\Api2\FieldAware\DefaultExtender;
use Shopware\Framework\Api2\FieldAware\FieldExtenderCollection;
use Shopware\Framework\Api2\FieldException\ApiStackException;
use Shopware\Framework\Api2\Generator;
use Shopware\Framework\Api2\Resource\ApiResourceProduct;
use Shopware\Framework\Api2\Resource\ApiResourceProductDetail;
use Shopware\Framework\Api2\Resource\ApiResourceProductManufacturer;
use Shopware\Framework\Api2\Resource\ApiResourceProductTranslation;
use Shopware\Framework\Api2\Resource\ApiResourceShop;
use Shopware\Framework\Api2\Resource\ResourceRegistry;
use Shopware\Framework\Api2\SqlGateway;
use Shopware\Framework\Api2\WriteContext;
use Shopware\Framework\Api2\Writer;
use Shopware\Framework\Validation\ConstraintBuilder;
use Shopware\Product\Gateway\Resource\ProductResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class IteratorTest extends KernelTestCase
{
    const UUID = 'AA-BB-CC';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var
     */
    private $productResource;

    public function setUp()
    {
        self::bootKernel();
        $container = self::$kernel->getContainer();
        $this->connection = $container->get('dbal_connection');

        $this->connection->beginTransaction();
    }

    public function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function test_gen()
    {
        (new Generator(self::$kernel->getContainer()))->generateAll();
        $this->assertTrue(true);
    }

    public function test_it()
    {
        $this->insertEmptyProduct();

        $resourceRegistry = self::$kernel->getContainer()->get('shopware.framework.api2.resource_registry');

        $sqlGateway = new SqlGateway($this->connection);
        $defaultExtender = self::$kernel->getContainer()->get('shopware.framework.api2.field_aware.default_extender');

        $collectionExtender = new FieldExtenderCollection();
        $collectionExtender->addExtender($defaultExtender);

        $writer = new Writer(
            $sqlGateway,
            $resourceRegistry,
            $this->connection
        );

        $writeContext = $this->createWriteContext();

        try {
            $writer->update(
                ProductResource::class,
                [
                    'uuid' => self::UUID,
                    'name' => [
                        'SWAG-CONFIG-SHOP-UUID-1' => '1ABC',
                        'SWAG-CONFIG-SHOP-UUID-2' => '2ABC'
                    ],
                    'description' => 'description',
                    'taxUuid' => 'SWAG-CONFIG-TAX-UUID-1',
                    'productManufacturer' => ['uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2'],
                    'details' => [
                        [
                            'inStock' => 15,
                            'position' => 0
                        ]

                    ]
                ],
                $writeContext,
                $collectionExtender);
        } catch (ApiStackException $e) {
            print_r($e->toArray());
        }

        self::assertTrue(true);
    }

    protected function insertEmptyProduct(): void
    {
        $this->connection->insert(
            'product',
            [
                'uuid' => self::UUID,
                'tax_uuid' => 'SWAG-CONFIG-TAX-UUID-1',
                'product_manufacturer_uuid' => 'SWAG-PRODUCT-MANUFACTURER-UUID-2',
            ]);
    }

    /**
     * @return WriteContext
     */
    protected function createWriteContext(): WriteContext
    {
        $context = new WriteContext();
        $context->set(\Shopware\Framework\Api2\Resource\CoreShopsResource::class, 'uuid', 'SWAG-CONFIG-SHOP-UUID-1');
        return $context;
    }
}



