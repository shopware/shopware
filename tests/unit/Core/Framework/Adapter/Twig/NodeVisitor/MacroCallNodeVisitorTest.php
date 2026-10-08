<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\NodeVisitor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\MacroCallExpression;
use Shopware\Core\Framework\Adapter\Twig\NodeVisitor\MacroCallNodeVisitor;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Node\Expression\ArrayExpression;
use Twig\Node\Expression\MacroReferenceExpression;
use Twig\Node\Expression\Variable\MacroVariable;
use Twig\Node\TextNode;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MacroCallNodeVisitor::class)]
class MacroCallNodeVisitorTest extends TestCase
{
    public function testWrapsMacroCallsOnLeave(): void
    {
        $visitor = new MacroCallNodeVisitor();
        $environment = static::createStub(Environment::class);
        $macro = new MacroReferenceExpression(
            new MacroVariable('_self', 1),
            'test',
            new ArrayExpression([], 1),
            1
        );

        static::assertSame($macro, $visitor->enterNode($macro, $environment));
        static::assertInstanceOf(MacroCallExpression::class, $visitor->leaveNode($macro, $environment));
    }

    public function testLeavesOtherNodesUnchanged(): void
    {
        $visitor = new MacroCallNodeVisitor();
        $node = new TextNode('text', 1);

        static::assertSame($node, $visitor->leaveNode($node, static::createStub(Environment::class)));
        static::assertSame(0, $visitor->getPriority());
    }
}
