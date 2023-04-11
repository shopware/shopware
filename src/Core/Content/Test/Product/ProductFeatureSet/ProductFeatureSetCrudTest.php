<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\ProductFeatureSet;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ProductFeatureSetCrudTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSetNullOnDelete(): void
    {
        $ids = new TestDataCollection();

        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'featureSet' => [
                'id' => $ids->create('feature-set'),
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Test feature set',
                        'description' => 'Lorem ipsum dolor sit amet',
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $exists = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT id FROM product_feature_set WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('feature-set'))]
            );

        static::assertEquals($exists, Uuid::fromHexToBytes($ids->get('feature-set')));

        $delete = ['id' => $ids->get('feature-set')];

        $this->getContainer()->get('product_feature_set.repository')
            ->delete([$delete], Context::createDefaultContext());

        $exists = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT id FROM product_feature_set WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('feature-set'))]
            );

        static::assertFalse($exists);

        $foreignKey = $this->getContainer()
            ->get(Connection::class)
            ->fetchOne(
                'SELECT product_feature_set_id FROM product WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('product'))]
            );

        static::assertNull($foreignKey);
    }

    public function testNameIsRequired(): void
    {
        $ids = new TestDataCollection();

        $data = [
            'id' => $ids->create('feature-set'),
        ];

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('This value should not be blank.');

        $this->getContainer()->get('product_feature_set.repository')
            ->create([$data], Context::createDefaultContext());
    }
}
