<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParser;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(TwigVariableParser::class)]
class TwigVariableParserTest extends TestCase
{
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

        $twig = new Environment(new ArrayLoader([]));
        $twig->addExtension(new DebugExtension());
        $parser = new TwigVariableParser($twig);

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

    public function testParserHandlesAssociationsInLoops(): void
    {
        $template = <<<TWIG
{{ product.name|striptags(product.stock) }}

{% for option in product.options %}
    {{ option.group.name }}
{% endfor %}

{% for options in product.options %}
    {{ options.group.name }}
{% endfor %}

{% for option in product.options %}
    {{ foo.group.name }}
{% endfor %}

TWIG;

        $parser = new TwigVariableParser(new Environment(new ArrayLoader([])));

        $variables = $parser->parse($template);

        $expected = [
            'foo.group.name',
            'product.name',
            'product.options',
            'product.options.group.name',
            'product.stock',
        ];

        sort($expected);
        sort($variables);

        static::assertEquals($expected, $variables);
    }
}
