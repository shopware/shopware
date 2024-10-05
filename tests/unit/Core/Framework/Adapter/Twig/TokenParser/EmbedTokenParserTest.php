<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\EmbedTokenParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(EmbedTokenParser::class)]
class EmbedTokenParserTest extends TestCase
{
    public function testRenderEmbedReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start embed end',
            $this->parseTemplate('{% sw_embed "foo.html.twig" %}{% block content %}embed{% endblock %}{% end_sw_embed %}')
        );
    }

    public function testNotRenderEmbedReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start inner end',
            $this->parseTemplate('{% sw_embed "foo.html.twig" %}{% block not_content %}embed{% endblock %}{% end_sw_embed %}')
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_embed',
            (new EmbedTokenParser($this->createMock(TemplateFinderInterface::class)))->getTag(),
        );
    }

    /**
     * @param string[] $scopes
     */
    private function parseTemplate(string $template): string
    {
        $templateName = Uuid::randomHex() . '.html.twig';
        $templateFinder = $this->createMock(TemplateFinderInterface::class);
        $templateFinder->expects(static::once())
            ->method('find')
            ->with('foo.html.twig', false, null)
            ->willReturn('bar.html.twig');

        $twig = new Environment(new ArrayLoader([
            $templateName => $template,
            'bar.html.twig' => 'start {% block content %}inner{% endblock %} end',
        ]));

        $twig->addTokenParser(new EmbedTokenParser($templateFinder));

        return $twig->render($templateName);
    }
}
