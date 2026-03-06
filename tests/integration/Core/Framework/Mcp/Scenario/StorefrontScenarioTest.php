<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\CartCheckoutTool;
use Shopware\Core\Framework\Mcp\Tool\CartManageTool;
use Shopware\Core\Framework\Mcp\Tool\CheckoutMethodsTool;
use Shopware\Core\Framework\Mcp\Tool\CustomerLookupTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CartManageTool::class)]
#[CoversClass(CartCheckoutTool::class)]
#[CoversClass(CheckoutMethodsTool::class)]
#[CoversClass(CustomerLookupTool::class)]
class StorefrontScenarioTest extends McpScenarioTestCase
{
    public function testUS13CartCreateAndAddProduct(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $product = (new ProductBuilder($ids, 'cart-product'))
            ->price(49.99)
            ->stock(100)
            ->visibility(TestDefaults::SALES_CHANNEL)
            ->build();

        static::getContainer()->get('product.repository')->create([$product], $context);

        $createOutput = ($this->cartManageTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            action: 'create',
        );

        $createData = $this->decodeToolOutput($createOutput);
        static::assertNotEmpty($createData['data']['token']);
        static::assertSame(0, $createData['data']['itemCount']);

        $token = $createData['data']['token'];

        $addOutput = ($this->cartManageTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            action: 'add',
            token: $token,
            productId: $ids->get('cart-product'),
            quantity: 2,
        );

        $addData = $this->decodeToolOutput($addOutput);
        static::assertGreaterThanOrEqual(1, $addData['data']['itemCount']);
        static::assertGreaterThan(0, $addData['data']['totalPrice']);
    }

    public function testUS13CartCheckoutDryRunAndCommit(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $product = (new ProductBuilder($ids, 'checkout-product'))
            ->price(19.99)
            ->stock(100)
            ->visibility(TestDefaults::SALES_CHANNEL)
            ->build();

        static::getContainer()->get('product.repository')->create([$product], $context);

        $email = 'mcp-us13-' . Uuid::randomHex() . '@example.com';
        $customer = (new CustomerBuilder($ids, 'US13'))
            ->add('email', $email)
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);

        $createOutput = ($this->cartManageTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            action: 'create',
        );
        $token = $this->decodeToolOutput($createOutput)['data']['token'];

        ($this->cartManageTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            action: 'add',
            token: $token,
            productId: $ids->get('checkout-product'),
            quantity: 1,
        );

        $dryRunOutput = ($this->cartCheckoutTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            token: $token,
            customerId: $ids->get('US13'),
            dryRun: true,
        );

        $dryRunData = $this->decodeToolOutput($dryRunOutput);
        static::assertTrue($dryRunData['_meta']['dryRun']);
        static::assertNotEmpty($dryRunData['data']['lineItems']);
        static::assertGreaterThan(0, $dryRunData['data']['totalPrice']);
        static::assertNotEmpty($dryRunData['data']['paymentMethodId']);
        static::assertNotEmpty($dryRunData['data']['shippingMethodId']);

        $commitOutput = ($this->cartCheckoutTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            token: $token,
            customerId: $ids->get('US13'),
            dryRun: false,
        );

        $commitData = $this->decodeToolOutput($commitOutput);
        static::assertFalse($commitData['_meta']['dryRun']);
        static::assertNotEmpty($commitData['data']['orderId']);
    }

    public function testUS14CheckoutMethods(): void
    {
        $output = ($this->checkoutMethodsTool)(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            type: 'all',
        );

        $data = $this->decodeToolOutput($output);

        static::assertNotEmpty($data['data']['paymentMethods']);
        static::assertNotEmpty($data['data']['shippingMethods']);

        $firstPayment = $data['data']['paymentMethods'][0];
        static::assertArrayHasKey('id', $firstPayment);
        static::assertArrayHasKey('name', $firstPayment);

        $firstShipping = $data['data']['shippingMethods'][0];
        static::assertArrayHasKey('id', $firstShipping);
        static::assertArrayHasKey('name', $firstShipping);
    }

    public function testUS15CustomerLookupByEmail(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $email = 'mcp-us15-' . Uuid::randomHex() . '@example.com';
        $customer = (new CustomerBuilder($ids, 'US15'))
            ->firstName('Jane')
            ->lastName('Doe')
            ->add('email', $email)
            ->add('password', TestDefaults::HASHED_PASSWORD)
            ->build();

        static::getContainer()->get('customer.repository')->create([$customer], $context);

        $output = ($this->customerLookupTool)(email: $email);
        $data = $this->decodeToolOutput($output);

        static::assertSame($email, $data['data']['email']);
        static::assertSame('Jane', $data['data']['firstName']);
        static::assertSame('Doe', $data['data']['lastName']);
        static::assertNotEmpty($data['data']['customerNumber']);
        static::assertIsArray($data['data']['recentOrders']);
    }
}
