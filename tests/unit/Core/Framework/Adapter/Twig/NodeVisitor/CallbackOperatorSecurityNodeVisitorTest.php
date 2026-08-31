<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\CallbackOperatorSecurityNodeVisitor;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\SecurityGuardedCallbackNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\Binary\HasEveryBinary;
use Twig\Node\Expression\Binary\HasSomeBinary;
use Twig\Node\Expression\ConstantExpression;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CallbackOperatorSecurityNodeVisitor::class)]
class CallbackOperatorSecurityNodeVisitorTest extends TestCase
{
    private CallbackOperatorSecurityNodeVisitor $visitor;

    private Environment $env;

    protected function setUp(): void
    {
        $this->visitor = new CallbackOperatorSecurityNodeVisitor();
        $this->env = new Environment(new ArrayLoader());
    }

    public function testHasSomeCallbackIsWrapped(): void
    {
        $node = new HasSomeBinary(new ConstantExpression('list', 1), new ConstantExpression('system', 1), 1);

        $this->visitor->enterNode($node, $this->env);

        static::assertInstanceOf(SecurityGuardedCallbackNode::class, $node->getNode('right'));
    }

    public function testHasEveryCallbackIsWrapped(): void
    {
        $node = new HasEveryBinary(new ConstantExpression('list', 1), new ConstantExpression('system', 1), 1);

        $this->visitor->enterNode($node, $this->env);

        static::assertInstanceOf(SecurityGuardedCallbackNode::class, $node->getNode('right'));
    }

    public function testUnrelatedNodeIsUntouched(): void
    {
        $node = new ConstantExpression('system', 1);

        static::assertSame($node, $this->visitor->enterNode($node, $this->env));
    }

    public function testCallbackIsNotWrappedTwice(): void
    {
        $callback = new ConstantExpression('system', 1);
        $node = new HasSomeBinary(new ConstantExpression('list', 1), $callback, 1);

        $this->visitor->enterNode($node, $this->env);
        $wrapped = $node->getNode('right');
        $this->visitor->enterNode($node, $this->env);

        static::assertSame($wrapped, $node->getNode('right'));
        static::assertSame($callback, $wrapped->getNode('callback'));
    }

    public function testLeaveNodeReturnsNodeUnchanged(): void
    {
        $node = new ConstantExpression('system', 1);

        static::assertSame($node, $this->visitor->leaveNode($node, $this->env));
    }

    public function testPriorityIsZero(): void
    {
        static::assertSame(0, $this->visitor->getPriority());
    }
}
