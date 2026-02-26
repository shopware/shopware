<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(PaymentMethodCollection::class)]
#[Package('checkout')]
class PaymentMethodCollectionTest extends TestCase
{
    public function testSortPaymentMethodsByPreferencePutsActiveMethodFirst(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();
        $otherMethodId = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 3);
        $defaultMethod = $this->createPaymentMethod($defaultMethodId, 1);
        $otherMethod = $this->createPaymentMethod($otherMethodId, 2);

        $collection = new PaymentMethodCollection([
            $otherMethod,
            $defaultMethod,
            $activeMethod,
        ]);

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($activeMethodId, $sortedIds[0]);
        static::assertSame($defaultMethodId, $sortedIds[1]);
        static::assertSame($otherMethodId, $sortedIds[2]);
    }

    public function testSortPaymentMethodsByPreferencePutsDefaultMethodSecond(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();
        $otherMethodId1 = Uuid::randomHex();
        $otherMethodId2 = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 4);
        $defaultMethod = $this->createPaymentMethod($defaultMethodId, 3);
        $otherMethod1 = $this->createPaymentMethod($otherMethodId1, 1);
        $otherMethod2 = $this->createPaymentMethod($otherMethodId2, 2);

        $collection = new PaymentMethodCollection([
            $otherMethod1,
            $otherMethod2,
            $defaultMethod,
            $activeMethod,
        ]);

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($activeMethodId, $sortedIds[0]);
        static::assertSame($defaultMethodId, $sortedIds[1]);
        static::assertSame($otherMethodId1, $sortedIds[2]);
        static::assertSame($otherMethodId2, $sortedIds[3]);
    }

    public function testSortPaymentMethodsByPreferenceWhenActiveIsDefault(): void
    {
        $activeAndDefaultMethodId = Uuid::randomHex();
        $otherMethodId1 = Uuid::randomHex();
        $otherMethodId2 = Uuid::randomHex();

        $activeAndDefaultMethod = $this->createPaymentMethod($activeAndDefaultMethodId, 3);
        $otherMethod1 = $this->createPaymentMethod($otherMethodId1, 1);
        $otherMethod2 = $this->createPaymentMethod($otherMethodId2, 2);

        $collection = new PaymentMethodCollection([
            $otherMethod1,
            $otherMethod2,
            $activeAndDefaultMethod,
        ]);

        $context = $this->createSalesChannelContext($activeAndDefaultMethod, $activeAndDefaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($activeAndDefaultMethodId, $sortedIds[0]);
        static::assertSame($otherMethodId1, $sortedIds[1]);
        static::assertSame($otherMethodId2, $sortedIds[2]);
    }

    public function testSortPaymentMethodsByPreferenceSortsByPositionForEqualPriority(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();
        $otherMethodId1 = Uuid::randomHex();
        $otherMethodId2 = Uuid::randomHex();
        $otherMethodId3 = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 5);
        $defaultMethod = $this->createPaymentMethod($defaultMethodId, 4);
        $otherMethod1 = $this->createPaymentMethod($otherMethodId1, 3);
        $otherMethod2 = $this->createPaymentMethod($otherMethodId2, 1);
        $otherMethod3 = $this->createPaymentMethod($otherMethodId3, 2);

        $collection = new PaymentMethodCollection([
            $activeMethod,
            $defaultMethod,
            $otherMethod1,
            $otherMethod2,
            $otherMethod3,
        ]);

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($activeMethodId, $sortedIds[0]);
        static::assertSame($defaultMethodId, $sortedIds[1]);
        static::assertSame($otherMethodId2, $sortedIds[2]);
        static::assertSame($otherMethodId3, $sortedIds[3]);
        static::assertSame($otherMethodId1, $sortedIds[4]);
    }

    public function testSortPaymentMethodsByPreferenceWithEmptyCollection(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 1);

        $collection = new PaymentMethodCollection();

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        static::assertCount(0, $collection);
    }

    public function testSortPaymentMethodsByPreferenceWithSingleElement(): void
    {
        $methodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();

        $method = $this->createPaymentMethod($methodId, 1);

        $collection = new PaymentMethodCollection([$method]);

        $context = $this->createSalesChannelContext($method, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertCount(1, $sortedIds);
        static::assertSame($methodId, $sortedIds[0]);
    }

    public function testSortPaymentMethodsByPreferenceActiveNotInCollection(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();
        $otherMethodId1 = Uuid::randomHex();
        $otherMethodId2 = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 1);
        $defaultMethod = $this->createPaymentMethod($defaultMethodId, 3);
        $otherMethod1 = $this->createPaymentMethod($otherMethodId1, 1);
        $otherMethod2 = $this->createPaymentMethod($otherMethodId2, 2);

        $collection = new PaymentMethodCollection([
            $defaultMethod,
            $otherMethod1,
            $otherMethod2,
        ]);

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($defaultMethodId, $sortedIds[0]);
        static::assertSame($otherMethodId1, $sortedIds[1]);
        static::assertSame($otherMethodId2, $sortedIds[2]);
    }

    public function testSortPaymentMethodsByPreferenceDefaultNotInCollection(): void
    {
        $activeMethodId = Uuid::randomHex();
        $defaultMethodId = Uuid::randomHex();
        $otherMethodId1 = Uuid::randomHex();
        $otherMethodId2 = Uuid::randomHex();

        $activeMethod = $this->createPaymentMethod($activeMethodId, 3);
        $otherMethod1 = $this->createPaymentMethod($otherMethodId1, 1);
        $otherMethod2 = $this->createPaymentMethod($otherMethodId2, 2);

        $collection = new PaymentMethodCollection([
            $activeMethod,
            $otherMethod1,
            $otherMethod2,
        ]);

        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($activeMethodId, $sortedIds[0]);
        static::assertSame($otherMethodId1, $sortedIds[1]);
        static::assertSame($otherMethodId2, $sortedIds[2]);
    }

    /**
     * @param array<array{id: string, position: int}> $inputMethods
     * @param array<string> $expectedOrder
     */
    #[DataProvider('providePaymentMethodSortingScenarios')]
    public function testSortPaymentMethodsByPreferenceWithDataProvider(
        array $inputMethods,
        string $activeMethodId,
        string $defaultMethodId,
        array $expectedOrder
    ): void {
        $methods = [];
        foreach ($inputMethods as $data) {
            $methods[] = $this->createPaymentMethod($data['id'], $data['position']);
        }

        $collection = new PaymentMethodCollection($methods);

        $activeMethod = $this->createPaymentMethod($activeMethodId, 0);
        $context = $this->createSalesChannelContext($activeMethod, $defaultMethodId);

        $collection->sortPaymentMethodsByPreference($context);

        $sortedIds = array_values($collection->getIds());

        static::assertSame($expectedOrder, $sortedIds);
    }

    /**
     * @return iterable<string, array{inputMethods: array<array{id: string, position: int}>, activeMethodId: string, defaultMethodId: string, expectedOrder: array<string>}>
     */
    public static function providePaymentMethodSortingScenarios(): iterable
    {
        $id1 = 'a0000000000000000000000000000001';
        $id2 = 'a0000000000000000000000000000002';
        $id3 = 'a0000000000000000000000000000003';
        $id4 = 'a0000000000000000000000000000004';

        yield 'all methods have same priority - sort by position' => [
            'inputMethods' => [
                ['id' => $id3, 'position' => 3],
                ['id' => $id1, 'position' => 1],
                ['id' => $id2, 'position' => 2],
            ],
            'activeMethodId' => 'other-active-id',
            'defaultMethodId' => 'other-default-id',
            'expectedOrder' => [$id1, $id2, $id3],
        ];

        yield 'active method has lowest position but highest priority' => [
            'inputMethods' => [
                ['id' => $id1, 'position' => 1],
                ['id' => $id2, 'position' => 2],
                ['id' => $id3, 'position' => 3],
            ],
            'activeMethodId' => $id3,
            'defaultMethodId' => 'other-default-id',
            'expectedOrder' => [$id3, $id1, $id2],
        ];

        yield 'default method sorted after active' => [
            'inputMethods' => [
                ['id' => $id1, 'position' => 1],
                ['id' => $id2, 'position' => 2],
                ['id' => $id3, 'position' => 3],
                ['id' => $id4, 'position' => 4],
            ],
            'activeMethodId' => $id4,
            'defaultMethodId' => $id1,
            'expectedOrder' => [$id4, $id1, $id2, $id3],
        ];

        yield 'active and default are same - no duplicates' => [
            'inputMethods' => [
                ['id' => $id1, 'position' => 1],
                ['id' => $id2, 'position' => 2],
                ['id' => $id3, 'position' => 3],
            ],
            'activeMethodId' => $id2,
            'defaultMethodId' => $id2,
            'expectedOrder' => [$id2, $id1, $id3],
        ];
    }

    public function testGetPluginIds(): void
    {
        $method1 = new PaymentMethodEntity();
        $method1->setId(Uuid::randomHex());
        $method1->setPluginId('plugin-1');

        $method2 = new PaymentMethodEntity();
        $method2->setId(Uuid::randomHex());
        $method2->setPluginId('plugin-2');

        $method3 = new PaymentMethodEntity();
        $method3->setId(Uuid::randomHex());

        $collection = new PaymentMethodCollection([$method1, $method2, $method3]);

        $pluginIds = $collection->getPluginIds();

        static::assertCount(2, $pluginIds);
        static::assertContains('plugin-1', $pluginIds);
        static::assertContains('plugin-2', $pluginIds);
    }

    public function testFilterByPluginId(): void
    {
        $method1 = new PaymentMethodEntity();
        $method1->setId(Uuid::randomHex());
        $method1->setPluginId('plugin-1');

        $method2 = new PaymentMethodEntity();
        $method2->setId(Uuid::randomHex());
        $method2->setPluginId('plugin-2');

        $method3 = new PaymentMethodEntity();
        $method3->setId(Uuid::randomHex());
        $method3->setPluginId('plugin-1');

        $collection = new PaymentMethodCollection([$method1, $method2, $method3]);

        $filtered = $collection->filterByPluginId('plugin-1');

        static::assertCount(2, $filtered);
        static::assertTrue($filtered->has($method1->getId()));
        static::assertFalse($filtered->has($method2->getId()));
        static::assertTrue($filtered->has($method3->getId()));
    }

    public function testGetApiAlias(): void
    {
        $collection = new PaymentMethodCollection();

        static::assertSame('payment_method_collection', $collection->getApiAlias());
    }

    private function createPaymentMethod(string $id, int $position): PaymentMethodEntity
    {
        $method = new PaymentMethodEntity();
        $method->setId($id);
        $method->setPosition($position);
        $method->setActive(true);

        return $method;
    }

    private function createSalesChannelContext(PaymentMethodEntity $activePaymentMethod, string $defaultPaymentMethodId): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $context = Generator::generateSalesChannelContext(
            salesChannel: $salesChannel,
            paymentMethod: $activePaymentMethod,
        );

        // Set default payment method after generator (generator overwrites it with active payment method)
        $context->getSalesChannel()->setPaymentMethodId($defaultPaymentMethodId);

        return $context;
    }
}
