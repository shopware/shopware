<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Tax\TaxDefinition;

class EntityAggregatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityAggregatorInterface
     */
    private $aggregator;

    protected function setUp()
    {
        $this->aggregator = $this->getContainer()->get(EntityAggregatorInterface::class);
    }

    public function testAggregateNonExistingShouldFail(): void
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage('Aggregation of type Shopware\Core\Framework\Test\DataAbstractionLayer\Search\TestAggregation not supported');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addAggregation(new TestAggregation('taxRate', 'foo'));

        $this->aggregator->aggregate(TaxDefinition::class, $criteria, $context);
    }
}
