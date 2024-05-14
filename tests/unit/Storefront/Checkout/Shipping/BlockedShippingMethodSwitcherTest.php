<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Shipping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlockedShippingMethodSwitcher::class)]
class BlockedShippingMethodSwitcherTest extends TestCase
{
    private ShippingMethodCollection $shippingMethodCollection;

    private SalesChannelContext $salesChannelContext;

    private BlockedShippingMethodSwitcher $switcher;

    protected function setUp(): void
    {
        $this->shippingMethodCollection = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign([
                'id' => 'original-shipping-method-id',
                'name' => 'original-shipping-method-name',
                'translated' => ['name' => 'original-shipping-method-name'],
            ]),
            (new ShippingMethodEntity())->assign([
                'id' => 'any-other-shipping-method-id',
                'name' => 'any-other-shipping-method-name',
                'translated' => ['name' => 'any-other-shipping-method-name'],
            ]),
            (new ShippingMethodEntity())->assign([
                'id' => 'default-shipping-method-id',
                'name' => 'default-shipping-method-name',
                'translated' => ['name' => 'default-shipping-method-name'],
            ]),
        ]);

        $this->salesChannelContext = $this->getSalesChannelContext();
        $this->switcher = new BlockedShippingMethodSwitcher(
            $this->getShippingMethodRoute()
        );
    }

    public function testSwitchDoesNotSwitchWithNoErrors(): void
    {
        $errorCollection = $this->getErrorCollection();
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('original-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }

    public function testSwitchBlockedOriginalSwitchToDefault(): void
    {
        $errorCollection = $this->getErrorCollection(['original-shipping-method-name']);
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('default-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'newShippingMethodName' => 'default-shipping-method-name',
            'oldShippingMethodName' => 'original-shipping-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalWithTranslatedName(): void
    {
        $errorCollection = $this->getErrorCollection(['original-shipping-method-name']);

        $this->shippingMethodCollection->remove('any-other-shipping-method-id');
        $this->shippingMethodCollection->remove('default-shipping-method-id');
        $this->shippingMethodCollection->add((new ShippingMethodEntity())->assign([
            'id' => 'translated-shipping-method-id',
            'name' => null,
            'translated' => ['name' => 'translated-shipping-method-name'],
        ]));

        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);
        static::assertSame('translated-shipping-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'newShippingMethodName' => 'translated-shipping-method-name',
            'oldShippingMethodName' => 'original-shipping-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection(['original-shipping-method-name', 'default-shipping-method-name']);
        $newShippingMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('any-other-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );
        static::assertCount(2, $errorCollectionFiltered);

        $expectedParameters = [
            [
                'newShippingMethodName' => 'any-other-shipping-method-name',
                'oldShippingMethodName' => 'original-shipping-method-name',
            ],
            [
                'newShippingMethodName' => 'any-other-shipping-method-name',
                'oldShippingMethodName' => 'default-shipping-method-name',
            ],
        ];

        foreach ($errorCollectionFiltered as $error) {
            static::assertContainsEquals($error->getParameters(), $expectedParameters);
        }
    }

    public function testSwitchBlockedOriginalAndNoDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection(['original-shipping-method-name']);
        $salesChannelContext = $this->getSalesChannelContext(true);
        $newShippingMethod = $this->switcher->switch($errorCollection, $salesChannelContext);

        static::assertSame('any-other-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'newShippingMethodName' => 'any-other-shipping-method-name',
            'oldShippingMethodName' => 'original-shipping-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultAndAnyOtherDoesNotSwitch(): void
    {
        $switcher = new BlockedShippingMethodSwitcher(
            $this->getShippingMethodRoute(true)
        );
        $errorCollection = $this->getErrorCollection(['original-shipping-method-name', 'default-shipping-method-name']);
        $newShippingMethod = $switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('original-shipping-method-id', $newShippingMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof ShippingMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }

    public function callbackLoadShippingMethods(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $searchIds = $criteria->getIds();

        if ($searchIds === []) {
            static::assertCount(1, $criteria->getFilters());

            $nand = $criteria->getFilters()[0];

            static::assertInstanceOf(NandFilter::class, $nand);
            static::assertCount(1, $nand->getQueries());

            $nameFilter = $nand->getQueries()[0];

            static::assertInstanceOf(EqualsAnyFilter::class, $nameFilter);

            $names = $nameFilter->getValue();

            $collection = $this->shippingMethodCollection->filter(
                fn (ShippingMethodEntity $entity) => !\in_array($entity->getName() ?? '', $names, true)
            );
        } else {
            $collection = $this->shippingMethodCollection->filter(
                fn (ShippingMethodEntity $entity) => \in_array($entity->getId(), $searchIds, true)
            );
        }

        $shippingMethodResponse = $this->createMock(ShippingMethodRouteResponse::class);
        $shippingMethodResponse
            ->expects(static::once())
            ->method('getShippingMethods')
            ->willReturn($collection);

        return $shippingMethodResponse;
    }

    public function callbackLoadShippingMethodsForAllBlocked(Request $request, SalesChannelContext $context, Criteria $criteria): ShippingMethodRouteResponse
    {
        $searchIds = $criteria->getIds();

        if ($searchIds === []) {
            $collection = new ShippingMethodCollection();
        } else {
            $collection = $this->shippingMethodCollection->filter(
                fn (ShippingMethodEntity $entity) => \in_array($entity->getId(), $searchIds, true)
            );
        }

        $shippingMethodResponse = $this->createMock(ShippingMethodRouteResponse::class);
        $shippingMethodResponse
            ->expects(static::once())
            ->method('getShippingMethods')
            ->willReturn($collection);

        return $shippingMethodResponse;
    }

    /**
     * @param array<string> $blockedShippingMethodNames
     */
    private function getErrorCollection(array $blockedShippingMethodNames = []): ErrorCollection
    {
        $errorCollection = new ErrorCollection();

        foreach ($blockedShippingMethodNames as $name) {
            $errorCollection->add(new ShippingMethodBlockedError($name));
        }

        return $errorCollection;
    }

    private function getSalesChannelContext(bool $dontReturnDefaultShippingMethod = false): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        if ($dontReturnDefaultShippingMethod) {
            $salesChannel->setShippingMethodId('not-a-valid-id');
        } else {
            $salesChannel->setShippingMethodId('default-shipping-method-id');
        }

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getShippingMethod')->willReturn($this->shippingMethodCollection->get('original-shipping-method-id'));

        return $salesChannelContext;
    }

    private function getShippingMethodRoute(bool $dontReturnAnyOtherShippingMethod = false): ShippingMethodRoute
    {
        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);

        if ($dontReturnAnyOtherShippingMethod) {
            $shippingMethodRoute
                ->method('load')
                ->withAnyParameters()
                ->willReturnCallback($this->callbackLoadShippingMethodsForAllBlocked(...));
        } else {
            $shippingMethodRoute
                ->method('load')
                ->withAnyParameters()
                ->willReturnCallback($this->callbackLoadShippingMethods(...));
        }

        return $shippingMethodRoute;
    }
}
