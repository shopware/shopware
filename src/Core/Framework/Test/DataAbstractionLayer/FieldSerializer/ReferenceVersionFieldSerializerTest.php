<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ReferenceVersionFieldSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUpdateSerialize(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->build();

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $connection = $this->getContainer()->get(Connection::class);

        $value = $connection->fetchOne('SELECT LOWER(HEX(product_manufacturer_version_id)) FROM product WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertEquals(Defaults::LIVE_VERSION, $value);

        $connection->executeStatement('UPDATE product SET product_manufacturer_version_id = NULL WHERE id = :id', ['id' => $ids->getBytes('p1')]);

        $value = $connection->fetchOne('SELECT LOWER(HEX(product_manufacturer_version_id)) FROM product WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertNull($value);

        $update = [
            'id' => $ids->get('p1'),
            'manufacturerId' => $ids->get('m1'),
        ];

        $this->getContainer()->get('product.repository')
            ->update([$update], Context::createDefaultContext());

        $value = $connection->fetchOne('SELECT LOWER(HEX(product_manufacturer_version_id)) FROM product WHERE id = :id', ['id' => $ids->getBytes('p1')]);
        static::assertEquals(Defaults::LIVE_VERSION, $value);
    }
}
