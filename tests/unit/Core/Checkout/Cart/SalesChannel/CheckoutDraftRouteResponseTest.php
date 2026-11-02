<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CheckoutDraft;
use Shopware\Core\Checkout\Cart\SalesChannel\CheckoutDraftRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutDraftRouteResponse::class)]
class CheckoutDraftRouteResponseTest extends TestCase
{
    public function testGetDraftReturnsTheDraftTheResponseWasCreatedWith(): void
    {
        $context = Generator::generateSalesChannelContext();

        $draft = new CheckoutDraft(
            cart: new Cart('token'),
            prospectiveCustomer: null,
            shippingMethod: $context->getShippingMethod(),
            paymentMethod: $context->getPaymentMethod(),
            currency: $context->getCurrency(),
            language: $context->getLanguageInfo(),
            shippingMethods: null,
            paymentMethods: null,
            shippingCosts: null,
        );

        static::assertSame($draft, (new CheckoutDraftRouteResponse($draft))->getDraft());
    }
}
