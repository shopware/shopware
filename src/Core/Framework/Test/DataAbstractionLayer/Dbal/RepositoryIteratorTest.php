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

        $expectedCriteriaJson = '{"sorting":[],"filters":[{"field":"configurationKey","value":"core","extensions":[]}],"postFilters":[],"aggregations":[],"queries":[],"groupFields":[],"offset":XXOFFSETXX,"limit":1,"totalCountMode":0,"associations":[],"ids":[],"states":[],"inherited":false,"term":null,"includes":null,"extensions":[]}';

        $x = 0;
        while (($result = $iterator->fetch()) !== null) {
            $expectedCriteriaJsonActual = str_replace('XXOFFSETXX', ++$x, $expectedCriteriaJson);
            static::assertNotEmpty($result->first()->getId());
            static::assertEquals($expectedCriteriaJsonActual, json_encode($criteria));
        }
    }
}
