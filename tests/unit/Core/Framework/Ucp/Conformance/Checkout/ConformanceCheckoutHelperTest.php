<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Conformance\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStateStore;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatus;
use Shopware\Core\Framework\Ucp\Conformance\Checkout\ConformanceCheckoutHelper;
use Shopware\Core\Framework\Ucp\Conformance\Checkout\ConformanceWebhookEmitter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ConformanceCheckoutHelper::class)]
class ConformanceCheckoutHelperTest extends TestCase
{
    protected function setUp(): void
    {
        putenv('UCP_CONFORMANCE_MODE=1');
    }

    protected function tearDown(): void
    {
        putenv('UCP_CONFORMANCE_MODE');
    }

    public function testIsActiveFalseInProdEvenWhenFlagSet(): void
    {
        $helper = $this->makeHelper(environment: 'prod');

        static::assertFalse($helper->isActive());
    }

    public function testIsActiveFalseInDevWithoutFlag(): void
    {
        putenv('UCP_CONFORMANCE_MODE');
        $helper = $this->makeHelper(environment: 'dev');

        static::assertFalse($helper->isActive());
    }

    public function testIsActiveTrueInDevWithFlag(): void
    {
        $helper = $this->makeHelper(environment: 'dev');

        static::assertTrue($helper->isActive());
    }

    public function testIsConformanceRequestRequiresHeaderAndActive(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $with = new Request();
        $with->headers->set('request-signature', 'test');
        $without = new Request();

        static::assertTrue($helper->isConformanceRequest($with));
        static::assertFalse($helper->isConformanceRequest($without));
    }

    public function testValidateLineItemsRejectsPinkWumpus(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $request = new Request();
        $request->headers->set('request-signature', 'test');

        $response = $helper->validateLineItems($request, [['item' => ['id' => 'pink_wumpus'], 'quantity' => 1]]);

        static::assertNotNull($response);
        static::assertSame(400, $response->getStatusCode());
        static::assertStringContainsString('Product not found', (string) $response->getContent());
    }

    public function testValidateLineItemsRejectsGardenias(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $request = new Request();
        $request->headers->set('request-signature', 'test');

        $response = $helper->validateLineItems($request, [['item' => ['id' => 'gardenias'], 'quantity' => 1]]);

        static::assertNotNull($response);
        static::assertSame(400, $response->getStatusCode());
        static::assertStringContainsString('Insufficient stock', (string) $response->getContent());
    }

    public function testValidateLineItemsRejectsExcessiveQuantity(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $request = new Request();
        $request->headers->set('request-signature', 'test');

        $response = $helper->validateLineItems($request, [['item' => ['id' => 'bouquet_roses'], 'quantity' => 101]]);

        static::assertNotNull($response);
        static::assertSame(400, $response->getStatusCode());
    }

    public function testValidateLineItemsAcceptsLegitItem(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $request = new Request();
        $request->headers->set('request-signature', 'test');

        $response = $helper->validateLineItems($request, [['item' => ['id' => 'bouquet_roses'], 'quantity' => 1]]);

        static::assertNull($response);
    }

    public function testValidateLineItemsIgnoresNonConformanceRequests(): void
    {
        $helper = $this->makeHelper(environment: 'dev');
        $request = new Request();

        // pink_wumpus would otherwise be rejected — without the conformance header it must pass through.
        $response = $helper->validateLineItems($request, [['item' => ['id' => 'pink_wumpus'], 'quantity' => 1]]);

        static::assertNull($response);
    }

    public function testPaymentFailureResponseDetectsFailToken(): void
    {
        $helper = $this->makeHelper(environment: 'dev');

        $response = $helper->paymentFailureResponse([
            'payment' => ['instruments' => [['token' => 'fail_token_xyz']]],
        ]);

        static::assertNotNull($response);
        static::assertSame(402, $response->getStatusCode());
    }

    public function testPaymentFailureResponseInactiveInProd(): void
    {
        $helper = $this->makeHelper(environment: 'prod');

        $response = $helper->paymentFailureResponse([
            'payment' => ['instruments' => [['token' => 'fail_token_xyz']]],
        ]);

        static::assertNull($response);
    }

    public function testShouldSkipDiscountCodeMatchesFixtures(): void
    {
        $helper = $this->makeHelper(environment: 'dev');

        static::assertTrue($helper->shouldSkipDiscountCode('10OFF'));
        static::assertTrue($helper->shouldSkipDiscountCode('WELCOME20'));
        static::assertTrue($helper->shouldSkipDiscountCode('FIXED500'));
        static::assertTrue($helper->shouldSkipDiscountCode('INVALID_CODE_XYZ'));
        static::assertFalse($helper->shouldSkipDiscountCode('REAL_PROMO_2026'));
    }

    public function testShouldSkipDiscountCodeAlwaysFalseInProd(): void
    {
        $helper = $this->makeHelper(environment: 'prod');

        static::assertFalse($helper->shouldSkipDiscountCode('10OFF'));
    }

    public function testTerminalCheckoutReturns409WhenCompleted(): void
    {
        $store = $this->createMock(CheckoutStateStore::class);
        $store->method('state')->with('ck_1')->willReturn(CheckoutStatus::COMPLETED);

        $helper = new ConformanceCheckoutHelper($store, $this->createMock(ConformanceWebhookEmitter::class), 'dev');
        $response = $helper->terminalCheckoutResponse('ck_1');

        static::assertNotNull($response);
        static::assertSame(409, $response->getStatusCode());
        static::assertStringContainsString('checkout_not_modifiable', (string) $response->getContent());
    }

    public function testTerminalCheckoutReturnsNullWhenIncomplete(): void
    {
        $store = $this->createMock(CheckoutStateStore::class);
        $store->method('state')->willReturn(CheckoutStatus::INCOMPLETE);

        $helper = new ConformanceCheckoutHelper($store, $this->createMock(ConformanceWebhookEmitter::class), 'dev');

        static::assertNull($helper->terminalCheckoutResponse('ck_1'));
    }

    public function testApplyOnCreatePersistsBuyerAndOverlaysStoredBuyer(): void
    {
        $store = $this->createMock(CheckoutStateStore::class);
        $store->expects($this->once())
            ->method('saveBuyer')
            ->with('ck_1', ['email' => 'john.doe@example.com']);
        $store->method('buyer')->with('ck_1')->willReturn(['email' => 'john.doe@example.com']);

        $helper = new ConformanceCheckoutHelper($store, $this->createMock(ConformanceWebhookEmitter::class), 'dev');

        $response = ['id' => 'ck_1'];
        $helper->applyOnCreate(['buyer' => ['email' => 'john.doe@example.com']], $response, 'ck_1');

        static::assertSame(['email' => 'john.doe@example.com'], $response['buyer']);
    }

    public function testApplyOnCompleteMarksCheckoutCompleted(): void
    {
        $store = $this->createMock(CheckoutStateStore::class);
        $store->expects($this->once())->method('markCompleted')->with('ck_1', 'order_1');
        $store->method('buyer')->willReturn(null);

        $helper = new ConformanceCheckoutHelper($store, $this->createMock(ConformanceWebhookEmitter::class), 'dev');
        $response = ['id' => 'ck_1'];
        $helper->applyOnComplete([], $response, 'ck_1', 'order_1');
    }

    public function testStoredFulfillmentReturnsNullInProd(): void
    {
        $store = $this->createMock(CheckoutStateStore::class);
        $store->expects($this->never())->method('fulfillmentForCheckout');

        $helper = new ConformanceCheckoutHelper($store, $this->createMock(ConformanceWebhookEmitter::class), 'prod');

        static::assertNull($helper->storedFulfillmentForCheckout('ck_1'));
    }

    public function testEmitOrderPlacedWebhookDelegatesOnlyForConformanceRequests(): void
    {
        $emitter = $this->createMock(ConformanceWebhookEmitter::class);
        $emitter->expects($this->once())
            ->method('emitOrderPlaced')
            ->with('https://platform.example/profile', 'ck_1', 'order_1', ['some' => 'response']);

        $helper = new ConformanceCheckoutHelper(
            $this->createMock(CheckoutStateStore::class),
            $emitter,
            'dev'
        );

        $request = new Request();
        $request->headers->set('request-signature', 'test');
        $helper->emitOrderPlacedWebhook($request, 'https://platform.example/profile', 'ck_1', 'order_1', ['some' => 'response']);
    }

    public function testEmitOrderPlacedWebhookSilentForNonConformanceRequest(): void
    {
        $emitter = $this->createMock(ConformanceWebhookEmitter::class);
        $emitter->expects($this->never())->method('emitOrderPlaced');

        $helper = new ConformanceCheckoutHelper(
            $this->createMock(CheckoutStateStore::class),
            $emitter,
            'dev'
        );

        $request = new Request();
        $helper->emitOrderPlacedWebhook($request, 'https://platform.example/profile', 'ck_1', 'order_1', []);
    }

    private function makeHelper(string $environment): ConformanceCheckoutHelper
    {
        return new ConformanceCheckoutHelper(
            $this->createMock(CheckoutStateStore::class),
            $this->createMock(ConformanceWebhookEmitter::class),
            $environment
        );
    }
}
