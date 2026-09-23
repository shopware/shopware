<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\SecurityGuardedCallbackNode;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SecurityGuardedCallbackNode::class)]
class SecurityGuardedCallbackNodeTest extends TestCase
{
    public function testKeepsCallbackAsChildNode(): void
    {
        $callback = new ConstantExpression('system', 1);
        $node = new SecurityGuardedCallbackNode($callback, 1);

        static::assertSame($callback, $node->getNode('callback'));
    }

    public function testCompilesToGuardCallbackCall(): void
    {
        $node = new SecurityGuardedCallbackNode(new ConstantExpression('system', 1), 1);

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $node->compile($compiler);

        static::assertSame(
            '$this->env->getExtension(\\' . SecurityExtension::class . '::class)->guardCallback("system")',
            $compiler->getSource()
        );
    }
}
