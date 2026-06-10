<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Node\FinderTemplateExpression;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FinderTemplateExpression::class)]
class FinderTemplateExpressionTest extends TestCase
{
    #[TestDox('It compiles to a runtime TemplateFinder lookup of the wrapped name expression')]
    public function testCompile(): void
    {
        $expression = new FinderTemplateExpression(new ConstantExpression('foo.html.twig', 1), 1);

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $compiler->compile($expression);

        static::assertSame(
            '$this->env->getExtension("Shopware\\\\Core\\\\Framework\\\\Adapter\\\\Twig\\\\Extension\\\\NodeExtension")->getFinder()->find("foo.html.twig")',
            $compiler->getSource()
        );
    }

    #[TestDox('It resolves a dynamic template name through the finder at render time and loads the result')]
    public function testRenderResolvesDynamicNameThroughFinder(): void
    {
        // sw_include only emits a FinderTemplateExpression once the v6.8.0.0 behavior is active;
        // before that the token parser still returns the deprecated SwInclude node.
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $templateName = Uuid::randomHex() . '.html.twig';

        $finder = $this->createMock(TemplateFinder::class);
        $finder->expects($this->once())
            ->method('find')
            ->with('@MyTheme/partial.html.twig')
            ->willReturn('resolved.html.twig');

        $twig = new Environment(new ArrayLoader([
            $templateName => '{% sw_include partial %}',
            'resolved.html.twig' => 'RESOLVED',
        ]));
        $twig->addExtension(new NodeExtension($finder, $this->createMock(TemplateScopeDetector::class)));

        static::assertSame(
            'RESOLVED',
            $twig->render($templateName, ['partial' => '@MyTheme/partial.html.twig'])
        );
    }
}
