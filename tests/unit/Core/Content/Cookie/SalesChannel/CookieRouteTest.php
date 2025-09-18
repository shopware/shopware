<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\CookieException;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieRoute::class)]
class CookieRouteTest extends TestCase
{
    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieRoute::class));

        $cookieProvider = $this->createMock(CookieProvider::class);
        (new CookieRoute($cookieProvider))->getDecorated();
    }

    public function testGetCookieGroups(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $expectedCookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->expects($this->once())
            ->method('getCookieGroups')
            ->with($salesChannelContext)
            ->willReturn($expectedCookieGroups);

        $response = (new CookieRoute($cookieProvider))->getCookieGroups(new Request(), $salesChannelContext);

        static::assertSame($expectedCookieGroups, $response->getCookieGroups());
        static::assertIsString($response->getHash());
        static::assertNotEmpty($response->getHash());
    }

    public function testHashIsConsistentForSameConfiguration(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($cookieGroups);

        $cookieRoute = new CookieRoute($cookieProvider);

        $response1 = $cookieRoute->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = $cookieRoute->getCookieGroups(new Request(), $salesChannelContext);

        static::assertSame($response1->getHash(), $response2->getHash());
    }

    public function testHashChangesWithDifferentCookieConfiguration(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // First configuration
        $cookieGroup1 = new CookieGroup('test.group.1');
        $cookieGroup1->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie-1')]));
        $cookieGroups1 = new CookieGroupCollection([$cookieGroup1]);

        // Second configuration with different cookie
        $cookieGroup2 = new CookieGroup('test.group.2');
        $cookieGroup2->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie-2')]));
        $cookieGroups2 = new CookieGroupCollection([$cookieGroup2]);

        $cookieProvider1 = $this->createMock(CookieProvider::class);
        $cookieProvider1->method('getCookieGroups')->willReturn($cookieGroups1);

        $cookieProvider2 = $this->createMock(CookieProvider::class);
        $cookieProvider2->method('getCookieGroups')->willReturn($cookieGroups2);

        $response1 = (new CookieRoute($cookieProvider1))->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = (new CookieRoute($cookieProvider2))->getCookieGroups(new Request(), $salesChannelContext);

        static::assertNotSame($response1->getHash(), $response2->getHash());
    }

    public function testHashIsConsistentRegardlessOfOrder(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create two groups that will be added in different orders
        $group1 = new CookieGroup('test.group.a');
        $group1->setEntries(new CookieEntryCollection([
            new CookieEntry('cookie-z'),
            new CookieEntry('cookie-a'),
        ]));

        $group2 = new CookieGroup('test.group.b');
        $group2->setEntries(new CookieEntryCollection([
            new CookieEntry('cookie-y'),
            new CookieEntry('cookie-b'),
        ]));

        // Collection 1: A, B order
        $collection1 = new CookieGroupCollection([$group1, $group2]);

        // Collection 2: B, A order (different insertion order)
        $collection2 = new CookieGroupCollection([$group2, $group1]);

        $cookieProvider1 = $this->createMock(CookieProvider::class);
        $cookieProvider1->method('getCookieGroups')->willReturn($collection1);

        $cookieProvider2 = $this->createMock(CookieProvider::class);
        $cookieProvider2->method('getCookieGroups')->willReturn($collection2);

        $response1 = (new CookieRoute($cookieProvider1))->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = (new CookieRoute($cookieProvider2))->getCookieGroups(new Request(), $salesChannelContext);

        // Hash should be the same regardless of collection order thanks to internal sorting
        static::assertSame($response1->getHash(), $response2->getHash(), 'Hash should be the same regardless of collection order');
    }

    public function testHashThrowsExceptionOnHashingFailure(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create a problematic cookie group that will cause hashing to fail
        $cookieGroup = new class('test') extends CookieGroup {
            public function jsonSerialize(): array
            {
                // Return something that will cause the Hasher to fail
                return [
                    'circular' => $this, // This will cause a circular reference error
                ];
            }
        };

        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($cookieGroups);

        $this->expectException(CookieException::class);
        $this->expectExceptionMessage('Failed to generate cookie configuration hash');

        (new CookieRoute($cookieProvider))->getCookieGroups(new Request(), $salesChannelContext);
    }

    public function testOriginalCookieGroupOrderIsPreserved(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create groups in a specific order: required, then others
        $requiredGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $requiredGroup->setEntries(new CookieEntryCollection([new CookieEntry('session')]));

        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $marketingGroup->setEntries(new CookieEntryCollection([new CookieEntry('marketing')]));

        $statisticalGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        $statisticalGroup->setEntries(new CookieEntryCollection([new CookieEntry('analytics')]));

        // Add in specific order: required first, then others
        $originalGroups = new CookieGroupCollection([
            $requiredGroup,
            $marketingGroup,
            $statisticalGroup,
        ]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($originalGroups);

        $response = (new CookieRoute($cookieProvider))->getCookieGroups(new Request(), $salesChannelContext);
        $returnedGroups = $response->getCookieGroups();

        // Verify that the original order is preserved
        $groupsArray = array_values($returnedGroups->getElements());
        static::assertSame($requiredGroup, $groupsArray[0], 'Required group should be first');
        static::assertSame($marketingGroup, $groupsArray[1], 'Marketing group should be second');
        static::assertSame($statisticalGroup, $groupsArray[2], 'Statistical group should be third');
    }

    public function testHashConsistencyWithRequiredGroupPriority(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create same groups in different orders
        $requiredGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_REQUIRED);
        $requiredGroup->setEntries(new CookieEntryCollection([new CookieEntry('session')]));

        $marketingGroup = new CookieGroup(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        $marketingGroup->setEntries(new CookieEntryCollection([new CookieEntry('marketing')]));

        // Collection 1: required first
        $collection1 = new CookieGroupCollection([$requiredGroup, $marketingGroup]);

        // Collection 2: required second
        $collection2 = new CookieGroupCollection([$marketingGroup, $requiredGroup]);

        $cookieProvider1 = $this->createMock(CookieProvider::class);
        $cookieProvider1->method('getCookieGroups')->willReturn($collection1);

        $cookieProvider2 = $this->createMock(CookieProvider::class);
        $cookieProvider2->method('getCookieGroups')->willReturn($collection2);

        $response1 = (new CookieRoute($cookieProvider1))->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = (new CookieRoute($cookieProvider2))->getCookieGroups(new Request(), $salesChannelContext);

        // Hash should be the same because internal sorting uses alphabetical order by technical name
        static::assertSame($response1->getHash(), $response2->getHash(), 'Hash should be consistent regardless of input order due to alphabetical sorting');
    }
}
