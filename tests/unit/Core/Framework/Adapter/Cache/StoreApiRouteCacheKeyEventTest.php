<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\StoreApiRouteCacheKeyEvent;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(StoreApiRouteCacheKeyEvent::class)]
class StoreApiRouteCacheKeyEventTest extends TestCase
{
    private SalesChannelContext $context;

    private Request $request;

    private StoreApiRouteCacheKeyEvent $defaultEvent;

    private SalesChannelEntity $salesChannelEntity;

    protected function setUp(): void
    {
        $this->request = new Request();
        $this->salesChannelEntity = new SalesChannelEntity();
        $this->salesChannelEntity->setId(Uuid::randomHex());
        $this->context = Generator::createSalesChannelContext(
            baseContext: new Context(new SalesChannelApiSource(Uuid::randomHex())),
            salesChannel: $this->salesChannelEntity
        );

        $this->defaultEvent = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, null);
    }

    public function testGetPartsWillReturnConstructorValue(): void
    {
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $event = new StoreApiRouteCacheKeyEvent($parts, $this->request, $this->context, null);
        static::assertEquals($parts, $event->getParts());
    }

    public function testSetPartsWillGetPartsReturnSetterValue(): void
    {
        static::assertEquals([], $this->defaultEvent->getParts());
        $parts = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->defaultEvent->setParts($parts);
        static::assertEquals($parts, $this->defaultEvent->getParts());
    }

    public function testGetRequestWillReturnCorrectRequest(): void
    {
        static::assertEquals($this->request, $this->defaultEvent->getRequest());
    }

    public function testGetCriteriaWithCriteriaWillReturnCriteria(): void
    {
        $criteria = new Criteria();
        $event = new StoreApiRouteCacheKeyEvent([], $this->request, $this->context, $criteria);
        static::assertEquals($criteria, $event->getCriteria());
    }

    public function testGetCriteriaWithNullInCriteriaWillReturnNull(): void
    {
        static::assertNull($this->defaultEvent->getCriteria());
    }

    public function testGetSalesChannelIdWillReturnChannelIdFromGivenContext(): void
    {
        static::assertEquals($this->salesChannelEntity->getId(), $this->defaultEvent->getSalesChannelId());
    }

    public function testDisableCachingWillDisableCache(): void
    {
        static::assertTrue($this->defaultEvent->shouldCache());
        $this->defaultEvent->disableCaching();
        static::assertFalse($this->defaultEvent->shouldCache());
    }
}
