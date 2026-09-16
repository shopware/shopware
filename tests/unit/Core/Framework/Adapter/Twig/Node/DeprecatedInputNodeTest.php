<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\DeprecatedInputNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeprecatedInputNode::class)]
class DeprecatedInputNodeTest extends TestCase
{
    public function testDeclarationDoesNotCompileRuntimeOutput(): void
    {
        $node = new DeprecatedInputNode('type', 'v6.8.0.0', 'addressType', null, 1);
        $compiler = new Compiler(new Environment(new ArrayLoader()));

        $compiler->compile($node);

        static::assertSame('', $compiler->getSource());
    }
}
