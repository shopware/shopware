<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class LineItemLabelTranslateTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider labelRenderingDataProvider
     */
    public function testLabelRendering(string $label, string $expected): void
    {
        $template = <<<TWIG
{% set label = item.label|trans({}, 'storefront') %}
{{ label }}
TWIG;

        $context = Context::createDefaultContext();

        $item = new LineItem('test', 'test');
        $item->setLabel($label);

        $result = $this->getContainer()->get(StringTemplateRenderer::class)
            ->render($template, ['item' => $item], $context);

        static::assertEquals($expected, $result);
    }

    public function labelRenderingDataProvider(): \Generator
    {
        yield 'Test normal label' => ['Some cool product name', 'Some cool product name'];
        yield 'Test with special chars' => ['Some cool ! % & product name', 'Some cool ! % &amp; product name'];
        yield 'Test existing snippet' => ['general.homeLink', 'Home'];
        yield 'Test none existing snippet' => ['general.homeLink-foo', 'general.homeLink-foo'];
    }
}
