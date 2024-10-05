<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\FromTokenParser;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(FromTokenParser::class)]
class FromTokenParserTest extends TestCase
{
    public function testRenderFromReferencingAnInheritedTemplate(): void
    {
        static::assertSame(
            'stuff from macro',
            $this->parseTemplate('{% sw_from "foo.html.twig" import do_stuff as stuff %}{{ stuff() }}')
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_from',
            (new FromTokenParser($this->createMock(TemplateFinderInterface::class)))->getTag(),
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
            'bar.html.twig' => '{% macro do_stuff() %}stuff from macro{% endmacro %}',
        ]));

        $twig->addTokenParser(new FromTokenParser($templateFinder));

        return $twig->render($templateName);
    }
}
