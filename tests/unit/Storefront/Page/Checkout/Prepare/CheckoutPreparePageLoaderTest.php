<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Prepare;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Translator\StaticTranslator;
use Shopware\Storefront\Page\Checkout\Prepare\CheckoutPreparePageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutPreparePageLoader::class)]
class CheckoutPreparePageLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $genericLoader = $this->createMock(GenericPageLoader::class);
        $eventDispatcher = new EventDispatcher();
        $cartService = $this->createMock(CartService::class);
        $translator = new StaticTranslator(['checkout.prepareMetaTitle' => 'checkout.prepareMetaTitle']);

        $page = new Page();
        $page->setMetaInformation(new MetaInformation());
        $genericLoader->method('load')->willReturn($page);

        $context = Generator::createSalesChannelContext();

        $cart = new Cart('test');
        $cartService
            ->expects(static::once())
            ->method('getCart')
            ->with($context->getToken(), $context)
            ->willReturn($cart);

        $loader = new CheckoutPreparePageLoader($genericLoader, $eventDispatcher, $cartService, $translator);

        $request = new Request();

        $result = $loader->load($request, $context);

        static::assertSame('checkout.prepareMetaTitle | ', $result->getMetaInformation()?->getMetaTitle());
        static::assertSame($cart, $result->getCart());
    }
}
