<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CalculatedPriceFieldTestDefinition;

/**
 * @internal
 */
class CalculatedPriceFieldTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;

    public function testListPrice(): void
    {
        $definition = $this->registerDefinition(CalculatedPriceFieldTestDefinition::class);
        $connection = $this->getContainer()->get(Connection::class);

        $connection->rollBack();
        $connection->executeStatement(CalculatedPriceFieldTestDefinition::getCreateTable());
        $connection->beginTransaction();

        $ids = new IdsCollection();

        $data = [
            'id' => $ids->create('entity'),
            'price' => new CalculatedPrice(
                100,
                100,
                new CalculatedTaxCollection([]),
                new TaxRuleCollection([]),
                1,
                null,
                ListPrice::createFromUnitPrice(100, 200)
            ),
        ];

        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $this->getContainer()->get(EntityWriter::class)
            ->insert($definition, [$data], $writeContext);

        $entity = $this->getContainer()->get(EntityReaderInterface::class)
            ->read($definition, new Criteria([$ids->get('entity')]), Context::createDefaultContext())
            ->get($ids->get('entity'));
        static::assertInstanceOf(ArrayEntity::class, $entity);

        $price = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $price);

        static::assertInstanceOf(ListPrice::class, $price->getListPrice());
        static::assertSame(200.0, $price->getListPrice()->getPrice());
        static::assertSame(-100.0, $price->getListPrice()->getDiscount());
        static::assertSame(50.0, $price->getListPrice()->getPercentage());
    }
}
