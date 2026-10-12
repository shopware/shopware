<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Node;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Node\ReturnNode;
use Shopware\Core\Framework\Log\Package;
use Twig\Compiler;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Node\Expression\ConstantExpression;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReturnNode::class)]
class ReturnNodeTest extends TestCase
{
    public function testCompileStoresExplicitResultBeforeReturning(): void
    {
        $node = new ReturnNode([
            'expr' => new ConstantExpression(['first', 'second'], 1),
        ], [], 1);

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $compiler->compile($node);

        $expected = <<<'PHP'
// line 1
\Shopware\Core\Framework\Adapter\Twig\SwTwigFunction::returnFromMacro([0 => "first", 1 => "second"]);
return;
PHP;
        $expected .= "\n";

        static::assertSame($expected, $compiler->getSource());
    }

    public function testCompileBareReturnDoesNotOverwriteResult(): void
    {
        $node = new ReturnNode([], [], 1);

        $compiler = new Compiler(new Environment(new ArrayLoader()));
        $compiler->compile($node);

        $expected = <<<'PHP'
// line 1
return;
PHP;
        $expected .= "\n";

        static::assertSame($expected, $compiler->getSource());
    }
}
