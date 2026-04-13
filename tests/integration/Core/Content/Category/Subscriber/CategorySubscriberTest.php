<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Test\Category\CategoryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class CategorySubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSalesChannelCategoryLoadedAssignsSeoUrl(): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        static::getContainer()->get('category.repository')->create(
            [(new CategoryBuilder($ids, 'c.1'))->build()],
            $context
        );

        $criteria = new Criteria([$ids->get('c.1')]);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $searchResult = static::getContainer()->get('sales_channel.category.repository')
            ->search($criteria, $salesChannelContext);

        $category = $searchResult->get($ids->get('c.1'));

        static::assertInstanceOf(SalesChannelCategoryEntity::class, $category);
        static::assertSame("124c71d524604ccbad6042edce3ac799/navigation/{$ids->get('c.1')}#", $category->getSeoUrl());
    }
}
