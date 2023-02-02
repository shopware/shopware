<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Store\Checkout\Shipping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;

/**
 * @internal
 * @covers \Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher
 */
class BlockedShippingMethodSwitcherTest extends TestCase
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
        $blockedShippingMethodSwitcher = $this->getBlockedShippingMethodSwitcher($targets);
        $errorCollection = $this->getErrorCollection($targets);
        $salesChannelContext = $this->getSalesChannelContext($targets);
        $newShippingMethod = $blockedShippingMethodSwitcher->switch($errorCollection, $salesChannelContext);

        if (\in_array('block', $targets, true)) {
            if (\in_array('empty-any-shipping-method', $targets, true)) {
                static::assertEquals($this->getOriginalShippingMethod(), $newShippingMethod);
            } elseif (\in_array('empty-default-shipping-method', $targets, true)) {
                static::assertEquals($this->getAnyShippingMethod(), $newShippingMethod);
            } elseif (\in_array('block-default-shipping-method', $targets, true)) {
                static::assertEquals($this->getAnyShippingMethod(), $newShippingMethod);
            } elseif (\in_array('block-original-shipping-method', $targets, true)) {
                static::assertEquals($this->getDefaultShippingMethod(), $newShippingMethod);
            }
        } else {
            static::assertEquals($this->getOriginalShippingMethod(), $newShippingMethod);
        }

        if (\in_array('check-notice', $targets, true)) {
            $shippingMethodChangedErrors = $errorCollection->filter(static function (Error $error) {
                return $error instanceof ShippingMethodChangedError;
            });

            $possibleErrors = [];
            if (\in_array('notice-original-to-default', $targets, true)) {
                array_push($possibleErrors, [
                    'newShippingMethodName' => $this->getDefaultShippingMethod()->getName(),
                    'oldShippingMethodName' => $this->getOriginalShippingMethod()->getName(),
                ]);
            }
            if (\in_array('notice-original-to-any', $targets, true)) {
                array_push($possibleErrors, [
                    'newShippingMethodName' => $this->getAnyShippingMethod()->getName(),
                    'oldShippingMethodName' => $this->getOriginalShippingMethod()->getName(),
                ]);
            }
            if (\in_array('notice-default-to-any', $targets, true)) {
                array_push($possibleErrors, [
                    'newShippingMethodName' => $this->getAnyShippingMethod()->getName(),
                    'oldShippingMethodName' => $this->getDefaultShippingMethod()->getName(),
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
                ['block', 'block-original-shipping-method', 'check-notice', 'notice-original-to-default'],
            ],
            [
                ['block', 'block-original-shipping-method', 'block-default-shipping-method', 'check-notice', 'notice-original-to-any', 'notice-default-to-any'],
            ],
            [
                ['block', 'block-original-shipping-method', 'empty-default-shipping-method', 'check-notice', 'notice-original-to-any'],
            ],
            [
                ['block', 'block-original-shipping-method', 'empty-default-shipping-method', 'empty-any-shipping-method'],
            ],
        ];
    }

    /**
     * @param array<string> $targets
     */
    private function getErrorCollection(array $targets): ErrorCollection
    {
        $errorCollection = new ErrorCollection();
        if (\in_array('block-original-shipping-method', $targets, true)) {
            $errorCollection->add(new ShippingMethodBlockedError($this->getOriginalShippingMethod()->getName() ?? ''));
        }
        if (\in_array('block-default-shipping-method', $targets, true)) {
            $errorCollection->add(new ShippingMethodBlockedError($this->getDefaultShippingMethod()->getName() ?? ''));
        }

        return $errorCollection;
    }

    /**
     * @param array<string> $targets
     */
    private function getSalesChannelContext(array $targets): SalesChannelContext
    {
        $shippingMethod = $this->getOriginalShippingMethod();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $salesChannel->setShippingMethodId($shippingMethod->getId());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);

        return $salesChannelContext;
    }

    private function getOriginalShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        return $shippingMethod;
    }

    private function getDefaultShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('default-shipping-method-id');
        $shippingMethod->setName('default-shipping-method-name');

        return $shippingMethod;
    }

    private function getAnyShippingMethod(): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('any-shipping-method-id');
        $shippingMethod->setName('any-shipping-method-name');

        return $shippingMethod;
    }

    /**
     * @param array<string> $targets
     */
    private function getBlockedShippingMethodSwitcher(array $targets): BlockedShippingMethodSwitcher
    {
        $shippingMethodRoute = $this->getShippingMethodRoute($targets);

        return new BlockedShippingMethodSwitcher($shippingMethodRoute);
    }

    /**
     * @param array<string> $targets
     */
    private function getShippingMethodRoute(array $targets): ShippingMethodRoute
    {
        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturnOnConsecutiveCalls(
            $this->getShippingMethodRouteResponse(
                \in_array('empty-default-shipping-method', $targets, true) ? null : $this->getDefaultShippingMethod()
            ),
            $this->getShippingMethodRouteResponse(
                \in_array('empty-any-shipping-method', $targets, true) ? null : $this->getAnyShippingMethod()
            )
        );

        return $shippingMethodRoute;
    }

    private function getShippingMethodRouteResponse(?ShippingMethodEntity $shippingMethod = null): ShippingMethodRouteResponse
    {
        $shippingMethodCollection = new ShippingMethodCollection();
        if ($shippingMethod !== null) {
            $shippingMethodCollection->add($shippingMethod);
        }
        $shippingMethodRouteResponse = $this->createMock(ShippingMethodRouteResponse::class);
        $shippingMethodRouteResponse->method('getShippingMethods')->willReturn($shippingMethodCollection);

        return $shippingMethodRouteResponse;
    }
}
