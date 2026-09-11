<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Generator;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
class HeaderTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHeaderEnablesOffCanvasCartFocusRestoration(): void
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->push(new Request());
        $context = Generator::generateSalesChannelContext();
        $context->assign(['customer' => null]);
        $context->getCurrency()->setIsoCode('EUR');

        try {
            $html = $twig->render('@Storefront/storefront/layout/header/header.html.twig', [
                'context' => $context,
            ]);
        } finally {
            $requestStack->pop();
        }

        $cart = (new Crawler($html))->filter('[data-off-canvas-cart]');
        static::assertCount(1, $cart);

        $options = json_decode($cart->attr('data-off-canvas-cart-options') ?? '{}', true, flags: \JSON_THROW_ON_ERROR);
        static::assertIsArray($options);
        static::assertTrue($options['autoFocus'] ?? false);
    }
}
