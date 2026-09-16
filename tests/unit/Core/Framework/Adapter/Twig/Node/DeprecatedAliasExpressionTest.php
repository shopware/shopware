<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedAliasExpression;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\Variable\ContextVariable;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedAliasExpression::class)]
class DeprecatedAliasExpressionTest extends TestCase
{
    public function testCompilesAliasResolutionAroundTheOriginalExpression(): void
    {
        $compiler = new Compiler(new Environment(new ArrayLoader()));

        $compiler->compile(new DeprecatedAliasExpression(new ContextVariable('type', 3)));

        static::assertStringContainsString('\\' . DeprecatedAlias::class . '::resolve(', $compiler->getSource());
        static::assertStringContainsString('($context["type"] ?? null)', $compiler->getSource());
    }
}
