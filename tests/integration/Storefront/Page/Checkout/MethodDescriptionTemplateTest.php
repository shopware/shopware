<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Checkout;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;

/**
 * @internal
 */
#[Package('checkout')]
class MethodDescriptionTemplateTest extends TestCase
{
    use KernelTestBehaviour;

    #[DataProvider('methodTemplates')]
    public function testOnlyTheSelectedMethodShowsItsFullDescription(
        string $template,
        string $methodVariable,
        string $selectedIdVariable,
        string $descriptionSelector,
    ): void {
        $description = 'This method includes important checkout information that remains visible in full after the customer selects it.';
        $method = [
            'id' => 'method-id',
            'media' => null,
            'translated' => [
                'name' => 'Method',
                'description' => $description,
            ],
        ];

        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $selected = new Crawler($twig->render($template, [
            $methodVariable => $method,
            $selectedIdVariable => 'method-id',
        ]));
        static::assertSame($description, $selected->filter($descriptionSelector)->text());

        $unselected = new Crawler($twig->render($template, [
            $methodVariable => $method,
            $selectedIdVariable => 'another-method-id',
        ]));
        static::assertCount(0, $unselected->filter($descriptionSelector));
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function methodTemplates(): iterable
    {
        yield 'payment method' => [
            '@Storefront/storefront/component/payment/payment-method.html.twig',
            'payment',
            'selectedPaymentMethodId',
            '.payment-method-description p',
        ];

        yield 'shipping method' => [
            '@Storefront/storefront/component/shipping/shipping-method.html.twig',
            'shipping',
            'selectedShippingMethodId',
            '.shipping-method-description p',
        ];
    }
}
