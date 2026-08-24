<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class CartRuleLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExistingCartLoadDoesNotRecreateDeletedCart(): void
    {
        $cartRuleLoader = static::getContainer()->get(CartRuleLoader::class);
        $cartPersister = static::getContainer()->get(CartPersister::class);

        $cart = new Cart(Uuid::randomHex());
        $cart->add(
            (new LineItem('A', LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()))
                ->setLabel('test')
        );

        $context = $this->createSalesChannelContext($cart->getToken());

        $cartPersister->save($cart, $context);

        $loadedCart = $cartPersister->load($cart->getToken(), $context);
        $loadedCart->setErrorHash('outdated');

        $cartPersister->delete($loadedCart->getToken(), $context);

        $result = $cartRuleLoader->loadByCart($context, $loadedCart, new CartBehavior());

        static::assertTrue($result->getCart()->has('A'));

        $count = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT COUNT(*) FROM cart WHERE token = :token',
            ['token' => $loadedCart->getToken()]
        );

        static::assertSame('0', (string) $count);
    }

    private function createSalesChannelContext(string $token): SalesChannelContext
    {
        return static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create($token, TestDefaults::SALES_CHANNEL);
    }
}
