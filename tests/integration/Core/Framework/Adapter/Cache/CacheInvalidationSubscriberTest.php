<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CacheInvalidationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    private CacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->subscriber = static::getContainer()->get(CacheInvalidationSubscriber::class);
        $this->ids = new TestDataCollection();
    }

    public function testInvalidateDetailRouteLoadsParentProductIds(): void
    {
        $scenarios = [
            'parent-product' => [
                'invalidate' => ['p1'],
                'expected' => ['p1'],
            ],
            'single-product-with-parent' => [
                'invalidate' => ['p2'],
                'expected' => ['p1', 'p2'],
            ],
            'multiple-products-with-parent' => [
                'invalidate' => ['p2', 'p3'],
                'expected' => ['p1', 'p2', 'p3'],
            ],
            'multiple-products-with-parent-and-without-parent' => [
                'invalidate' => ['p2', 'p3', 'p4'],
                'expected' => ['p1', 'p2', 'p3', 'p4'],
            ],
            'parent-and-child' => [
                'invalidate' => ['p1', 'p2'],
                'expected' => ['p1', 'p2'],
            ],
            'multiple-child-same-parent' => [
                'invalidate' => ['p1', 'p2', 'p3'],
                'expected' => ['p1', 'p2', 'p3'],
            ],
        ];

        $this->createProduct($this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p2'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p3'), $this->ids->getBytes('p1'));
        $this->createProduct($this->ids->getBytes('p4'));

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $listener = new class() {
            /**
             * @param array<string> $tags
             */
            public function __construct(public array $tags = [])
            {
            }

            public function __invoke(InvalidateCacheEvent $event): void
            {
                $this->tags = array_values($event->getKeys());
            }
        };

        $eventDispatcher->addListener(InvalidateCacheEvent::class, $listener);

        foreach ($scenarios as $scenario) {
            /** @var list<string> $productsIds */
            $productsIds = array_map(fn (string $product) => $this->ids->get($product), $scenario['invalidate']);

            $this->subscriber->invalidateDetailRoute(new ProductNoLongerAvailableEvent(
                $productsIds,
                Context::createDefaultContext()
            ));

            static::assertSame(
                array_map(fn (string $product) => 'product-detail-route-' . $this->ids->get($product), $scenario['expected']),
                $listener->tags
            );
        }
    }

    private function createProduct(string $id, ?string $parentId = null): void
    {
        $product = [
            'id' => $id,
            'parent_id' => $parentId,
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'stock' => 5,
            'available_stock' => 5,
        ];

        $this->connection->insert('product', $product);
    }
}
