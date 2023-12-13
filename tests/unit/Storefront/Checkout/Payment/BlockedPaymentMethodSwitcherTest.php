<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlockedPaymentMethodSwitcher::class)]
class BlockedPaymentMethodSwitcherTest extends TestCase
{
    private PaymentMethodCollection $paymentMethodCollection;

    private SalesChannelContext $salesChannelContext;

    private BlockedPaymentMethodSwitcher $switcher;

    protected function setUp(): void
    {
        $this->paymentMethodCollection = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign([
                'id' => 'original-payment-method-id',
                'name' => 'original-payment-method-name',
                'translated' => ['name' => 'original-payment-method-name'],
            ]),
            (new PaymentMethodEntity())->assign([
                'id' => 'any-other-payment-method-id',
                'name' => 'any-other-payment-method-name',
                'translated' => ['name' => 'any-other-payment-method-name'],
            ]),
            (new PaymentMethodEntity())->assign([
                'id' => 'default-payment-method-id',
                'name' => 'default-payment-method-name',
                'translated' => ['name' => 'default-payment-method-name'],
            ]),
        ]);

        $this->salesChannelContext = $this->getSalesChannelContext();
        $this->switcher = new BlockedPaymentMethodSwitcher(
            $this->getPaymentMethodRoute()
        );
    }

    public function testSwitchDoesNotSwitchWithNoErrors(): void
    {
        $errorCollection = $this->getErrorCollection();
        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('original-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }

    public function testSwitchBlockedOriginalSwitchToDefault(): void
    {
        $errorCollection = $this->getErrorCollection(['original-payment-method-name']);
        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('default-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'newPaymentMethodName' => 'default-payment-method-name',
            'oldPaymentMethodName' => 'original-payment-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalWithTranslatedName(): void
    {
        $errorCollection = $this->getErrorCollection(['original-payment-method-name']);

        $this->paymentMethodCollection->remove('any-other-payment-method-id');
        $this->paymentMethodCollection->remove('default-payment-method-id');
        $this->paymentMethodCollection->add((new PaymentMethodEntity())->assign([
            'id' => 'translated-payment-method-id',
            'name' => null,
            'translated' => ['name' => 'translated-payment-method-name'],
        ]));

        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);
        static::assertSame('translated-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );
        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'newPaymentMethodName' => 'translated-payment-method-name',
            'oldPaymentMethodName' => 'original-payment-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection(['original-payment-method-name', 'default-payment-method-name']);
        $newPaymentMethod = $this->switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('any-other-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );
        static::assertCount(2, $errorCollectionFiltered);

        $expectedParameters = [
            [
                'newPaymentMethodName' => 'any-other-payment-method-name',
                'oldPaymentMethodName' => 'original-payment-method-name',
            ],
            [
                'newPaymentMethodName' => 'any-other-payment-method-name',
                'oldPaymentMethodName' => 'default-payment-method-name',
            ],
        ];

        foreach ($errorCollectionFiltered as $error) {
            static::assertContainsEquals($error->getParameters(), $expectedParameters);
        }
    }

    public function testSwitchBlockedOriginalAndNoDefaultSwitchToAnyOther(): void
    {
        $errorCollection = $this->getErrorCollection(['original-payment-method-name']);
        $salesChannelContext = $this->getSalesChannelContext(true);
        $newPaymentMethod = $this->switcher->switch($errorCollection, $salesChannelContext);

        static::assertSame('any-other-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );

        static::assertCount(1, $errorCollectionFiltered);
        $error = $errorCollectionFiltered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'newPaymentMethodName' => 'any-other-payment-method-name',
            'oldPaymentMethodName' => 'original-payment-method-name',
        ], $error->getParameters());
    }

    public function testSwitchBlockedOriginalAndDefaultAndAnyOtherDoesNotSwitch(): void
    {
        $switcher = new BlockedPaymentMethodSwitcher(
            $this->getPaymentMethodRoute(true)
        );
        $errorCollection = $this->getErrorCollection(['original-payment-method-name', 'default-payment-method-name']);
        $newPaymentMethod = $switcher->switch($errorCollection, $this->salesChannelContext);

        static::assertSame('original-payment-method-id', $newPaymentMethod->getId());

        // Assert notices
        $errorCollectionFiltered = $errorCollection->filter(
            fn ($error) => $error instanceof PaymentMethodChangedError
        );

        static::assertCount(0, $errorCollectionFiltered);
    }

    public function callbackLoadPaymentMethods(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
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

            $collection = $this->paymentMethodCollection->filter(
                fn (PaymentMethodEntity $entity) => !\in_array($entity->getName() ?? '', $names, true)
            );
        } else {
            $collection = $this->paymentMethodCollection->filter(
                fn (PaymentMethodEntity $entity) => \in_array($entity->getId(), $searchIds, true)
            );
        }

        $paymentMethodResponse = $this->createMock(PaymentMethodRouteResponse::class);
        $paymentMethodResponse
            ->expects(static::once())
            ->method('getPaymentMethods')
            ->willReturn($collection);

        return $paymentMethodResponse;
    }

    public function callbackLoadPaymentMethodsForAllBlocked(Request $request, SalesChannelContext $context, Criteria $criteria): PaymentMethodRouteResponse
    {
        $searchIds = $criteria->getIds();

        if ($searchIds === []) {
            $collection = new PaymentMethodCollection();
        } else {
            $collection = $this->paymentMethodCollection->filter(
                fn (PaymentMethodEntity $entity) => \in_array($entity->getId(), $searchIds, true)
            );
        }

        $paymentMethodResponse = $this->createMock(PaymentMethodRouteResponse::class);
        $paymentMethodResponse
            ->expects(static::once())
            ->method('getPaymentMethods')
            ->willReturn($collection);

        return $paymentMethodResponse;
    }

    /**
     * @param array<string> $blockedPaymentMethodNames
     */
    private function getErrorCollection(array $blockedPaymentMethodNames = []): ErrorCollection
    {
        $errorCollection = new ErrorCollection();

        foreach ($blockedPaymentMethodNames as $name) {
            $errorCollection->add(new PaymentMethodBlockedError($name, 'Payment method blocked'));
        }

        return $errorCollection;
    }

    private function getSalesChannelContext(bool $dontReturnDefaultPaymentMethod = false): SalesChannelContext
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        if ($dontReturnDefaultPaymentMethod) {
            $salesChannel->setPaymentMethodId('not-a-valid-id');
        } else {
            $salesChannel->setPaymentMethodId('default-payment-method-id');
        }

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $salesChannelContext->method('getPaymentMethod')->willReturn($this->paymentMethodCollection->get('original-payment-method-id'));

        return $salesChannelContext;
    }

    private function getPaymentMethodRoute(bool $dontReturnAnyOtherPaymentMethod = false): PaymentMethodRoute
    {
        $paymentMethodRoute = $this->createMock(PaymentMethodRoute::class);

        if ($dontReturnAnyOtherPaymentMethod) {
            $paymentMethodRoute
                ->method('load')
                ->withAnyParameters()
                ->willReturnCallback($this->callbackLoadPaymentMethodsForAllBlocked(...));
        } else {
            $paymentMethodRoute
                ->method('load')
                ->withAnyParameters()
                ->willReturnCallback($this->callbackLoadPaymentMethods(...));
        }

        return $paymentMethodRoute;
    }
}
