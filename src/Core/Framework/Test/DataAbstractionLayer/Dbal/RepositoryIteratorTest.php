<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class RepositoryIteratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIteratedSearch(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'core'));
        $criteria->setLimit(1);

        $iterator = new RepositoryIterator($systemConfigRepository, $context, $criteria);

        $offset = 1;
        while (($result = $iterator->fetch()) !== null) {
            static::assertNotEmpty($result->first()->getId());
            static::assertEquals(
                [new ContainsFilter('configurationKey', 'core')],
                $criteria->getFilters()
            );
            static::assertCount(0, $criteria->getPostFilters());
            static::assertEquals($offset, $criteria->getOffset());
            ++$offset;
        }
    }

    public function testFetchIdsIsNotRunningInfinitely(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepositoryInterface $systemConfigRepository */
        $systemConfigRepository = $this->getContainer()->get('system_config.repository');

        $iterator = new RepositoryIterator($systemConfigRepository, $context, new Criteria());

        $iteration = 0;
        while ($iterator->fetchIds() !== null && $iteration < 100) {
            ++$iteration;
        }

        static::assertTrue($iteration < 100);
    }
}
