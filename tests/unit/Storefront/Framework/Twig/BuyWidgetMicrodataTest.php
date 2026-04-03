<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
class BuyWidgetMicrodataTest extends TestCase
{
    public function testDepthMicrodataUsesDepthItemProp(): void
    {
        $twig = $this->createTwigEnvironment();

        $product = new class () {
            public ?float $length = 12.0;

            public object $measurements;

            public function __construct()
            {
                $this->measurements = new class () {
                    public function type(string $type): ?object
                    {
                        return null;
                    }
                };
            }
        };

        $output = $twig->render('test.html.twig', ['product' => $product]);
        $normalizedOutput = preg_replace('/\s+/', ' ', trim($output));

        static::assertIsString($normalizedOutput);
        static::assertSame('<meta itemprop="depth" content="12 mm">', $normalizedOutput);
    }

    private function createTwigEnvironment(): Environment
    {
        $storefrontLoader = new FilesystemLoader();
        $storefrontLoader->addPath($this->getStorefrontViewPath(), 'Storefront');

        $loader = new ChainLoader([
            new ArrayLoader([
                'test.html.twig' => <<<'TWIG'
{% extends '@Storefront/storefront/component/buy-widget/buy-widget.html.twig' %}

{% block buy_widget %}
    {{ block('buy_widget_rich_snippets') }}
{% endblock %}

{% block buy_widget_rich_snippets %}
    {{ block('buy_widget_rich_snippets_depth') }}
{% endblock %}

{% block buy_widget_rich_snippets_depth %}
    {{ parent() }}
{% endblock %}
TWIG,
                '@Storefront/storefront/component/buy-widget/buy-widget-price.html.twig' => '',
                '@Storefront/storefront/component/buy-widget/configurator.html.twig' => '',
                '@Storefront/storefront/component/buy-widget/buy-widget-form.html.twig' => '',
            ]),
            $storefrontLoader,
        ]);

        $twig = new Environment($loader, ['cache' => false]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('twig_test_', true),
            new NamespaceHierarchyBuilder([]),
            new TemplateScopeDetector(new RequestStack())
        );

        $twig->addExtension(new NodeExtension($templateFinder, new TemplateScopeDetector(new RequestStack())));
        $twig->addFunction(new TwigFunction('feature', static fn (string $flag): bool => false));
        $twig->addFunction(new TwigFunction('seoUrl', static fn (string $name, array $parameters = []): string => '/'));
        $twig->addFunction(new TwigFunction('path', static fn (string $name, array $parameters = []): string => '/'));
        $twig->addFunction(new TwigFunction('config', static fn (string $name) => null));
        $twig->addFilter(new \Twig\TwigFilter('format_date', static fn ($date, string $pattern = 'Y-MM-dd'): string => (string) $date));
        $twig->addFilter(new \Twig\TwigFilter('trans', static fn ($value, ...$args) => $value));
        $twig->addFilter(new \Twig\TwigFilter('sw_sanitize', static fn ($value) => $value));
        $twig->addFilter(new \Twig\TwigFilter('currency', static fn ($value) => $value));

        return $twig;
    }

    private function getStorefrontViewPath(): string
    {
        return \dirname(__DIR__, 5) . '/src/Storefront/Resources/views';
    }
}
