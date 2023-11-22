<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Adapter\Twig\TokenParser\ExtendsTokenParser;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(ExtendsTokenParser::class)]
class ExtendsTokenParserTest extends TestCase
{
    public function testParseStringExtendsInMatchingScope(): void
    {
        static::assertSame(
            'extended',
            $this->parseTemplate(
                '{% sw_extends "foo.html.twig" %}{% block test %}extended{% endblock %}',
                [TemplateScopeDetector::DEFAULT_SCOPE],
                false
            )
        );
    }

    public function testParseStringNotExtendsInNonMatchingScope(): void
    {
        static::assertSame(
            'original',
            $this->parseTemplate(
                '{% sw_extends "foo.html.twig" %}{% block test %}extended{% endblock %}',
                ['foo'],
                false
            )
        );
    }

    public function testParseStringExtendsInNonMatchingScopeButInStorefront(): void
    {
        static::assertSame(
            'extended',
            $this->parseTemplate(
                '{% sw_extends "foo.html.twig" %}{% block test %}extended{% endblock %}',
                ['foo'],
                true
            )
        );
    }

    public function testParseWithObjectExtendsInMatchingScope(): void
    {
        static::assertSame(
            'extended',
            $this->parseTemplate(
                '{% sw_extends { template: "foo.html.twig", scopes: "foo" } %}{% block test %}extended{% endblock %}',
                ['foo'],
                false
            )
        );
    }

    public function testParseWithObjectAndArrayExtendsInMatchingScope(): void
    {
        static::assertSame(
            'extended',
            $this->parseTemplate(
                '{% sw_extends { template: "foo.html.twig", scopes: [ "foo" ] } %}{% block test %}extended{% endblock %}',
                ['foo'],
                false
            )
        );
    }

    public function testParseWithInvalidDataThrowsException(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Unexpected Expression of type "Twig\\\\Node\\\\Expression\\\\Unary\\\\NotUnary"/');

        $this->parseTemplate(
            '{% sw_extends { template: "foo.html.twig", scopes: not "foo" } %}{% block test %}extended{% endblock %}',
            ['foo'],
            false,
            false,
            false,
        );
    }

    public function testParseWithInvalidObjectKeyThrowsException(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Unexpected Expression of type "Twig\\\\Node\\\\Expression\\\\Unary\\\\NotUnary"/');

        $this->parseTemplate(
            '{% sw_extends { template: "foo.html.twig", (not foo): "foo" } %}{% block test %}extended{% endblock %}',
            ['foo'],
            false,
            false,
            false,
        );
    }

    public function testParseWithMissingTemplateThrowsException(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Template "[0-9a-f]{32}.html.twig" does not have an extending template./');

        $this->parseTemplate(
            '{% sw_extends { scopes: "foo" } %}{% block test %}extended{% endblock %}',
            ['foo'],
            false,
            false,
            false,
        );
    }

    public function testParseWithMissingScopeUsesDefaultScope(): void
    {
        static::assertSame(
            'extended',
            $this->parseTemplate(
                '{% sw_extends { template: "foo.html.twig" } %}{% block test %}extended{% endblock %}',
                [TemplateScopeDetector::DEFAULT_SCOPE],
                false,
            )
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_extends',
            (new ExtendsTokenParser(
                $this->createMock(TemplateFinderInterface::class),
                $this->createMock(TemplateScopeDetector::class),
            ))->getTag()
        );
    }

    /**
     * @param string[] $scopes
     */
    private function parseTemplate(
        string $template,
        array $scopes,
        bool $withStorefrontPrefix,
        bool $callsTemplateFinder = true,
        bool $callsScopeDetector = true
    ): string {
        $templateName = ($withStorefrontPrefix ? '@Storefront/' : '') . Uuid::randomHex() . '.html.twig';

        $templateFinder = $this->createMock(TemplateFinderInterface::class);
        $templateFinder->expects($callsTemplateFinder ? static::once() : static::never())
            ->method('find')
            ->with('foo.html.twig', false, $templateName)
            ->willReturn('bar.html.twig');

        $detector = $this->createMock(TemplateScopeDetector::class);
        $detector->expects($callsScopeDetector ? static::once() : static::never())
            ->method('getScopes')
            ->willReturn($scopes);

        $twig = new Environment(new ArrayLoader([
            $templateName => $template,
            'bar.html.twig' => '{% block test %}original{% endblock %}',
        ]));

        $twig->addTokenParser(new ExtendsTokenParser($templateFinder, $detector));

        return $twig->render($templateName);
    }
}
