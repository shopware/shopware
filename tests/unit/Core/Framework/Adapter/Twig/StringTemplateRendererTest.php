<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(StringTemplateRenderer::class)]
class StringTemplateRendererTest extends TestCase
{
    #[DataProvider('labelRenderingDataProvider')]
    public function testTranslationRendering(string $label, string $expected): void
    {
        $template = <<<'TWIG'
{% set label = item.label|trans({}, 'storefront') %}
{{ label }}
TWIG;

        $context = Context::createDefaultContext();

        $item = new LineItem('test', 'test');
        $item->setLabel($label);

        $environment = new Environment(new ArrayLoader());
        $translator = $this->createMock(Translator::class);
        $translator
            ->method('trans')
            ->willReturnCallback(static function (string $id) {
                if ($id === 'general.homeLink') {
                    return 'Home';
                }

                return $id;
            });

        $environment->addExtension(new TranslationExtension($translator));

        $renderer = new StringTemplateRenderer($environment, sys_get_temp_dir());
        $result = $renderer->render($template, ['item' => $item], $context);

        static::assertEquals($expected, $result);
    }

    public static function labelRenderingDataProvider(): \Generator
    {
        yield 'Test normal label' => ['Some cool product name', 'Some cool product name'];
        yield 'Test with special chars' => ['Some cool ! % & product name', 'Some cool ! % &amp; product name'];
        yield 'Test existing snippet' => ['general.homeLink', 'Home'];
        yield 'Test none existing snippet' => ['general.homeLink-foo', 'general.homeLink-foo'];
    }

    public function testRenderThrowsAdapterExceptionDueInvalidSyntax(): void
    {
        $template = <<<'TWIG'
{{ label }
TWIG;

        $context = Context::createDefaultContext();
        $environment = new Environment(new ArrayLoader());

        $renderer = new StringTemplateRenderer($environment, sys_get_temp_dir());

        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Failed rendering Twig string template due syntax error: "Unexpected "}" in "04e92a9efc07ae62e1ec342418711bbd" at line 1."');
        $renderer->render($template, [], $context);
    }
}
