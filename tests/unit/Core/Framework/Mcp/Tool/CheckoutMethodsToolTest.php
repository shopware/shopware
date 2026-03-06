<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\CheckoutMethodsTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheckoutMethodsTool::class)]
class CheckoutMethodsToolTest extends TestCase
{
    public function testPaymentTypeReturnsOnlyPaymentMethods(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'payment');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayHasKey('paymentMethods', $data['data']);
        static::assertArrayNotHasKey('shippingMethods', $data['data']);
        static::assertCount(2, $data['data']['paymentMethods']);
        static::assertSame('Invoice', $data['data']['paymentMethods'][0]['name']);
    }

    public function testShippingTypeReturnsOnlyShippingMethods(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'shipping');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayNotHasKey('paymentMethods', $data['data']);
        static::assertArrayHasKey('shippingMethods', $data['data']);
        static::assertCount(1, $data['data']['shippingMethods']);
        static::assertSame('Standard Shipping', $data['data']['shippingMethods'][0]['name']);
    }

    public function testAllTypeReturnsBoth(): void
    {
        $tool = $this->createTool(
            paymentMethods: $this->createPaymentMethods(),
            shippingMethods: $this->createShippingMethods(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'all');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayHasKey('paymentMethods', $data['data']);
        static::assertArrayHasKey('shippingMethods', $data['data']);
    }

    public function testEmptyMethodsLists(): void
    {
        $tool = $this->createTool(
            paymentMethods: new PaymentMethodCollection(),
            shippingMethods: new ShippingMethodCollection(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'all');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame([], $data['data']['paymentMethods']);
        static::assertSame([], $data['data']['shippingMethods']);
    }

    public function testInvalidTypeReturnsError(): void
    {
        $tool = $this->createTool(
            paymentMethods: new PaymentMethodCollection(),
            shippingMethods: new ShippingMethodCollection(),
        );

        $output = ($tool)(salesChannelId: Uuid::randomHex(), type: 'invalid');
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid type', $data['error']);
    }

    private function createTool(PaymentMethodCollection $paymentMethods, ShippingMethodCollection $shippingMethods): CheckoutMethodsTool
    {
        $context = $this->createMock(SalesChannelContext::class);

        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->method('get')->willReturn($context);

        $paymentResult = new EntitySearchResult(
            'payment_method',
            $paymentMethods->count(),
            $paymentMethods,
            null,
            new Criteria(),
            $context->getContext(),
        );
        $paymentResponse = new PaymentMethodRouteResponse($paymentResult);

        $paymentRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentRoute->method('load')->willReturn($paymentResponse);

        $shippingResult = new EntitySearchResult(
            'shipping_method',
            $shippingMethods->count(),
            $shippingMethods,
            null,
            new Criteria(),
            $context->getContext(),
        );
        $shippingResponse = new ShippingMethodRouteResponse($shippingResult);

        $shippingRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingRoute->method('load')->willReturn($shippingResponse);

        return new CheckoutMethodsTool($contextService, $paymentRoute, $shippingRoute);
    }

    private function createPaymentMethods(): PaymentMethodCollection
    {
        $pm1 = new PaymentMethodEntity();
        $pm1->setId(Uuid::randomHex());
        $pm1->setTranslated(['name' => 'Invoice', 'description' => 'Pay by invoice']);
        $pm1->setActive(true);
        $pm1->setPosition(1);

        $pm2 = new PaymentMethodEntity();
        $pm2->setId(Uuid::randomHex());
        $pm2->setTranslated(['name' => 'Credit Card', 'description' => 'Pay by credit card']);
        $pm2->setActive(true);
        $pm2->setPosition(2);

        return new PaymentMethodCollection([$pm1, $pm2]);
    }

    private function createShippingMethods(): ShippingMethodCollection
    {
        $sm = new ShippingMethodEntity();
        $sm->setId(Uuid::randomHex());
        $sm->setTranslated(['name' => 'Standard Shipping', 'description' => '3-5 business days']);
        $sm->setActive(true);

        return new ShippingMethodCollection([$sm]);
    }
}
