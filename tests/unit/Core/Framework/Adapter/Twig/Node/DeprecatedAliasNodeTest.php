<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedAliasNode;
use Shopware\Core\Framework\Adapter\Twig\Runtime\DeprecatedAlias;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedAliasNode::class)]
class DeprecatedAliasNodeTest extends TestCase
{
    public function testCompilesCompatibilityAliasAssignment(): void
    {
        $compiler = new Compiler(new Environment(new ArrayLoader()));

        $compiler->compile(new DeprecatedAliasNode('type', 'v6.8.0.0', 'addressType', 3));

        $source = $compiler->getSource();
        static::assertStringContainsString('$context["type"] = new \\' . DeprecatedAlias::class, $source);
        static::assertStringContainsString('($context["addressType"] ?? null)', $source);
        static::assertStringContainsString('The \\"type\\" Twig variable is deprecated. Use \\"addressType\\" instead.', $source);
    }
}
