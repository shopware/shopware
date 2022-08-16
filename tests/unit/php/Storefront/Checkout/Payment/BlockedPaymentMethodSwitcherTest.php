<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Store\Checkout\Payment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;

/**
 * @internal
 * @covers \Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher
 */
class BlockedPaymentMethodSwitcherTest extends TestCase
{
    protected function setUp(): void
    {
    }

    /**
     * @dataProvider getSwitchData
     *
     * @param array<string> $targets
     */
    public function testSwitch(array $targets): void
    {
        $blockedPaymentMethodSwitcher = $this->getBlockedPaymentMethodSwitcher($targets);
        $errorCollection = $this->getErrorCollection($targets);
        $salesChannelContext = $this->getSalesChannelContext($targets);
        $newPaymentMethod = $blockedPaymentMethodSwitcher->switch($errorCollection, $salesChannelContext);

        if (\in_array('block', $targets, true)) {
            if (\in_array('empty-any-payment-method', $targets, true)) {
                static::assertEquals($this->getOriginalPaymentMethod(), $newPaymentMethod);
            } elseif (\in_array('empty-default-payment-method', $targets, true)) {
                static::assertEquals($this->getAnyPaymentMethod(), $newPaymentMethod);
            } elseif (\in_array('block-default-payment-method', $targets, true)) {
                static::assertEquals($this->getAnyPaymentMethod(), $newPaymentMethod);
            } elseif (\in_array('block-original-payment-method', $targets, true)) {
                static::assertEquals($this->getDefaultPaymentMethod(), $newPaymentMethod);
            }
        } else {
            static::assertEquals($this->getOriginalPaymentMethod(), $newPaymentMethod);
        }

        if (\in_array('check-notice', $targets, true)) {
            $shippingMethodChangedErrors = $errorCollection->filter(static function (Error $error) {
                return $error instanceof PaymentMethodChangedError;
            });

            $possibleErrors = [];
            if (\in_array('notice-original-to-default', $targets, true)) {
                array_push($possibleErrors, [
                    'newPaymentMethodName' => $this->getDefaultPaymentMethod()->getName(),
                    'oldPaymentMethodName' => $this->getOriginalPaymentMethod()->getName(),
                ]);
            }
            if (\in_array('notice-original-to-any', $targets, true)) {
                array_push($possibleErrors, [
                    'newPaymentMethodName' => $this->getAnyPaymentMethod()->getName(),
                    'oldPaymentMethodName' => $this->getOriginalPaymentMethod()->getName(),
                ]);
            }
            if (\in_array('notice-default-to-any', $targets, true)) {
                array_push($possibleErrors, [
                    'newPaymentMethodName' => $this->getAnyPaymentMethod()->getName(),
                    'oldPaymentMethodName' => $this->getDefaultPaymentMethod()->getName(),
                ]);
            }

            static::assertCount(\count($possibleErrors), $shippingMethodChangedErrors);

            foreach ($shippingMethodChangedErrors as $error) {
                static::assertContains($error->getParameters(), $possibleErrors);
            }
        }
    }

    /**
     * @return array<int, array<int, array<int, string>>>
     */
    public function getSwitchData(): array
    {
        return [
            [
                [''],
            ],
            [
                ['block', 'block-original-payment-method', 'check-notice', 'notice-original-to-default'],
            ],
            [
                ['block', 'block-original-payment-method', 'block-default-payment-method', 'check-notice', 'notice-original-to-any', 'notice-default-to-any'],
            ],
            [
                ['block', 'block-original-payment-method', 'empty-default-payment-method', 'check-notice', 'notice-original-to-any'],
            ],
            [
                ['block', 'block-original-payment-method', 'empty-default-payment-method', 'empty-any-payment-method'],
            ],
        ];
    }

    /**
     * @param array<string> $targets
     */
    private function getErrorCollection(array $targets): ErrorCollection
    {
        $errorCollection = new ErrorCollection();
        if (\in_array('block-original-payment-method', $targets, true)) {
            $errorCollection->add(new PaymentMethodBlockedError($this->getOriginalPaymentMethod()->getName() ?? ''));
        }
        if (\in_array('block-default-payment-method', $targets, true)) {
            $errorCollection->add(new PaymentMethodBlockedError($this->getDefaultPaymentMethod()->getName() ?? ''));
        }

        return $errorCollection;
    }

    /**
     * @param array<string> $targets
     */
    private function getSalesChannelContext(array $targets): SalesChannelContext
    {
        $shippingMethod = $this->getOriginalPaymentMethod();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $salesChannel->setPaymentMethodId($shippingMethod->getId());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $salesChannelContext->method('getPaymentMethod')->willReturn($shippingMethod);

        return $salesChannelContext;
    }

    private function getOriginalPaymentMethod(): PaymentMethodEntity
    {
        $shippingMethod = new PaymentMethodEntity();
        $shippingMethod->setId('original-payment-method-id');
        $shippingMethod->setName('original-payment-method-name');

        return $shippingMethod;
    }

    private function getDefaultPaymentMethod(): PaymentMethodEntity
    {
        $shippingMethod = new PaymentMethodEntity();
        $shippingMethod->setId('default-payment-method-id');
        $shippingMethod->setName('default-payment-method-name');

        return $shippingMethod;
    }

    private function getAnyPaymentMethod(): PaymentMethodEntity
    {
        $shippingMethod = new PaymentMethodEntity();
        $shippingMethod->setId('any-payment-method-id');
        $shippingMethod->setName('any-payment-method-name');

        return $shippingMethod;
    }

    /**
     * @param array<string> $targets
     */
    private function getBlockedPaymentMethodSwitcher(array $targets): BlockedPaymentMethodSwitcher
    {
        $shippingMethodRoute = $this->getPaymentMethodRoute($targets);

        return new BlockedPaymentMethodSwitcher($shippingMethodRoute);
    }

    /**
     * @param array<string> $targets
     */
    private function getPaymentMethodRoute(array $targets): PaymentMethodRoute
    {
        $shippingMethodRoute = $this->createMock(PaymentMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturnOnConsecutiveCalls(
            $this->getPaymentMethodRouteResponse(
                \in_array('empty-default-payment-method', $targets, true) ? null : $this->getDefaultPaymentMethod()
            ),
            $this->getPaymentMethodRouteResponse(
                \in_array('empty-any-payment-method', $targets, true) ? null : $this->getAnyPaymentMethod()
            )
        );

        return $shippingMethodRoute;
    }

    private function getPaymentMethodRouteResponse(?PaymentMethodEntity $shippingMethod = null): PaymentMethodRouteResponse
    {
        $shippingMethodCollection = new PaymentMethodCollection();
        if ($shippingMethod !== null) {
            $shippingMethodCollection->add($shippingMethod);
        }
        $shippingMethodRouteResponse = $this->createMock(PaymentMethodRouteResponse::class);
        $shippingMethodRouteResponse->method('getPaymentMethods')->willReturn($shippingMethodCollection);

        return $shippingMethodRouteResponse;
    }
}
