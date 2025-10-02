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
        $cookieProvider->method('getCookieGroups')
            ->with(static::isInstanceOf(Request::class), $salesChannelContext)
            ->willReturn($expectedCookieGroups);

        $cookieRoute = new CookieRoute($cookieProvider);

        $response1 = $cookieRoute->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = $cookieRoute->getCookieGroups(new Request(), $salesChannelContext);

        // Verify basic functionality
        static::assertSame($expectedCookieGroups, $response1->getCookieGroups());
        static::assertIsString($response1->getHash());
        static::assertNotEmpty($response1->getHash());

        // Verify hash consistency for same configuration
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

    public function testHashIgnoresExtendedProperties(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create standard configuration
        $standardEntry = new CookieEntry('test-cookie');
        $standardGroup = new CookieGroup('test.group');
        $standardGroup->setEntries(new CookieEntryCollection([$standardEntry]));
        $standardGroups = new CookieGroupCollection([$standardGroup]);

        // Create configuration with extended CookieGroup and CookieEntry objects
        $extendedGroup = new class('test.group') extends CookieGroup {
            public string $dynamicProperty = 'this-should-not-affect-hash';

            public int $timestamp;

            /**
             * @var array<string, array<string, array<int, string>>>
             */
            public array $complexData = ['nested' => ['array' => ['structure']]];

            public object $objectProperty;

            public ?\Closure $callableProperty;

            public function __construct(string $technicalName)
            {
                parent::__construct($technicalName);
                $this->timestamp = time();
                $this->objectProperty = new \stdClass();
                $this->objectProperty->dynamic = random_int(1, 1000);
                $this->callableProperty = fn () => 'dynamic';
            }
        };

        $extendedEntry = new class('test-cookie') extends CookieEntry {
            public string $extraData = 'this-should-not-affect-hash';

            /**
             * @var array<string, string>
             */
            public array $metadata = ['key' => 'value'];

            public \DateTimeInterface $createdAt;

            /**
             * @var array<int, int>
             */
            public array $largeArray;

            public function __construct(string $cookie)
            {
                parent::__construct($cookie);
                $this->createdAt = new \DateTime();
                $this->largeArray = range(1, 100);
            }
        };

        $extendedGroup->setEntries(new CookieEntryCollection([$extendedEntry]));
        $extendedGroups = new CookieGroupCollection([$extendedGroup]);

        $standardProvider = $this->createMock(CookieProvider::class);
        $standardProvider->method('getCookieGroups')->willReturn($standardGroups);

        $extendedProvider = $this->createMock(CookieProvider::class);
        $extendedProvider->method('getCookieGroups')->willReturn($extendedGroups);

        $standardResponse = (new CookieRoute($standardProvider))->getCookieGroups(new Request(), $salesChannelContext);
        $extendedResponse = (new CookieRoute($extendedProvider))->getCookieGroups(new Request(), $salesChannelContext);

        // Hash should be the same despite extended properties
        static::assertSame(
            $standardResponse->getHash(),
            $extendedResponse->getHash(),
            'Hash should be robust against extended object properties'
        );
    }

    public function testHashChangesWhenDefinedPropertiesChange(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create base cookie group
        $baseGroup = new CookieGroup('test.group');
        $baseGroup->description = 'Original description';
        $baseEntry = new CookieEntry('test-cookie');
        $baseEntry->name = 'Original name';
        $baseGroup->setEntries(new CookieEntryCollection([$baseEntry]));
        $groups1 = new CookieGroupCollection([$baseGroup]);

        // Clone the group and change only one defined property
        $modifiedGroup = clone $baseGroup;
        $modifiedGroup->description = 'Modified description'; // Only change this property
        $groups2 = new CookieGroupCollection([$modifiedGroup]);

        $provider1 = $this->createMock(CookieProvider::class);
        $provider1->method('getCookieGroups')->willReturn($groups1);

        $provider2 = $this->createMock(CookieProvider::class);
        $provider2->method('getCookieGroups')->willReturn($groups2);

        $response1 = (new CookieRoute($provider1))->getCookieGroups(new Request(), $salesChannelContext);
        $response2 = (new CookieRoute($provider2))->getCookieGroups(new Request(), $salesChannelContext);

        // Hash should be different when defined properties change
        static::assertNotSame(
            $response1->getHash(),
            $response2->getHash(),
            'Hash should change when defined properties are modified'
        );
    }

    public function testThrowsCookieExceptionWhenHashGenerationFails(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        // Create a cookie entry with malformed UTF-8 data that will cause json_encode to fail
        $malformedEntry = new CookieEntry("test-cookie\xB1\x31"); // Invalid UTF-8 sequence
        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([$malformedEntry]));
        $cookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')
            ->with(static::isInstanceOf(Request::class), $salesChannelContext)
            ->willReturn($cookieGroups);

        $cookieRoute = new CookieRoute($cookieProvider);

        $this->expectExceptionObject(
            CookieException::hashGenerationFailed('Cookie configuration processing failed: JSON is invalid')
        );

        $cookieRoute->getCookieGroups(new Request(), $salesChannelContext);
    }
}
