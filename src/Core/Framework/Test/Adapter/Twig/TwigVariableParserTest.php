<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TwigVariableParserTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testParser(): void
    {
        $template = <<<TWIG
{% if product.price.gross == null %}
    {{ dump(product.prices) }}
{% endif %}

{{ product.name|striptags(product.stock) }}

{% set temp = product.translated.name %}

{% set temp = product.manufacturer.cover.id %}

{% include 'content.html.twig' with {'foo': 'bar', 'media': product.media} %}

TWIG;

        $parser = $this->getContainer()->get(TwigVariableParser::class);

        $variables = $parser->parse($template);

        $expected = [
            'product.price.gross',
            'product.prices',
            'product.name',
            'product.stock',
            'product.translated.name',
            'product.manufacturer.cover.id',
            'product.media',
        ];

        sort($expected);
        sort($variables);

        static::assertEquals($expected, $variables);
    }
}
