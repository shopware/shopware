<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\SwBlockReferenceExpression;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SwBlockReferenceExpression::class)]
class SwBlockReferenceExpressionTest extends TestCase
{
    public function testRenderBlockReferencingFromInheritedTemplate(): void
    {
        static::assertSame(
            'content',
            $this->parseTemplate('{{ sw_block("inner", "foo.html.twig") }}')
        );
    }

    public function testGetTag(): void
    {
        $extension = new TwigFeaturesWithInheritanceExtension($this->createMock(TemplateFinder::class));
        $functionNames = \array_map(
            fn (TwigFunction $function) => $function->getName(),
            $extension->getFunctions(),
        );

        static::assertContains('sw_block', $functionNames);
    }

    private function parseTemplate(string $template): string
    {
        $templateName = Uuid::randomHex() . '.html.twig';
        $templateFinder = $this->createMock(TemplateFinder::class);
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
