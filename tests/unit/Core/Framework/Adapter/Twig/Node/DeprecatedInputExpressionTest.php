<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputExpression;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\Variable\ContextVariable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputExpression::class)]
class DeprecatedInputExpressionTest extends TestCase
{
    public function testCompilesLazyAccess(): void
    {
        $node = new DeprecatedInputExpression(
            new ContextVariable('type', 3),
            'v6.8.0.0',
            'Use addressType instead.',
        );
        $compiler = new Compiler(new Environment(new ArrayLoader()));

        $compiler->compile($node);

        $expected = <<<'PHP'
\Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedInputRuntime::access("v6.8.0.0", "Use addressType instead.", fn () => // line 3
($context["type"] ?? null))
PHP;

        static::assertSame($expected, $compiler->getSource());
    }
}
