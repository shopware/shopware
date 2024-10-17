<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\SwBlockReferenceExpression;
use Shopware\Core\Framework\Adapter\Twig\SwTwigFunction;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(SwBlockReferenceExpression::class)]
#[CoversClass(SwTwigFunction::class)]
#[CoversClass(TwigFeaturesWithInheritanceExtension::class)]
class TwigFeaturesWithInheritanceExtensionTest extends TestCase
{
    public function testRenderBlockReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'content',
            $this->parseTemplate('{{ sw_block("inner", "foo.html.twig") }}')
        );
    }

    public function testRenderSourceReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start {% block inner %}content{% endblock %} end',
            $this->parseTemplate('{{ sw_source("foo.html.twig") }}')
        );
    }

    public function testRenderIncludeReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'start content end',
            $this->parseTemplate('{{ sw_include("foo.html.twig") }}')
        );
    }

    public function testGetTag(): void
    {
        $extension = new TwigFeaturesWithInheritanceExtension($this->createMock(TemplateFinderInterface::class));
        $functionNames = \array_map(
            fn (TwigFunction $function) => $function->getName(),
            $extension->getFunctions(),
        );

        static::assertContains('sw_block', $functionNames);
        static::assertContains('sw_source', $functionNames);
        static::assertContains('sw_include', $functionNames);
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
            'bar.html.twig' => 'start {% block inner %}content{% endblock %} end',
        ]));
        $twig->addExtension(new NodeExtension(
            $templateFinder,
            $this->createMock(TemplateScopeDetector::class),
        ));
        $twig->addExtension(new TwigFeaturesWithInheritanceExtension($templateFinder));

        return $twig->render($templateName);
    }
}
