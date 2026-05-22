<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;

/**
 * @internal
 */
#[CoversClass(LogicDetector::class)]
class LogicDetectorTest extends TestCase
{
    #[TestDox('methodContainsLogic($_dataName)')]
    #[DataProvider('caseProvider')]
    public function testMethodContainsLogic(string $body, bool $expected): void
    {
        static::assertSame($expected, LogicDetector::methodContainsLogic($this->parseMethod($body)));
    }

    /**
     * @return \Generator<string, array{0: string, 1: bool}>
     */
    public static function caseProvider(): \Generator
    {
        yield 'empty body' => ['', false];
        yield 'pure property read' => ['return $this->name;', false];
        yield 'property assignment' => ['$this->name = $name;', false];
        yield 'array offset write' => ['$this->arr[$k] = $v;', false];
        yield 'array offset read with coalesce' => ['return $this->arr[$k] ?? null;', false];
        yield 'method call alone' => ['$this->dep->call();', false];
        yield 'function call alone' => ['sprintf("%s", $x);', false];
        yield 'static call alone' => ['parent::__construct($x);', false];
        yield 'instantiation' => ['return new \stdClass();', false];
        yield 'arithmetic' => ['return $a + $b;', false];
        yield 'coalesce' => ['return $a ?? null;', false];
        yield 'comparison' => ['return $a === $b;', false];

        yield 'if statement' => ['if ($x) { return 1; }', true];
        yield 'elseif chain' => ['if ($x) {} elseif ($y) {}', true];
        yield 'while loop' => ['while ($x) { break; }', true];
        yield 'do while' => ['do {} while ($x);', true];
        yield 'for loop' => ['for ($i = 0; $i < 1; $i++) {}', true];
        yield 'foreach' => ['foreach ([] as $i) {}', true];
        yield 'switch' => ['switch ($x) { case 1: break; }', true];
        yield 'match expression' => ['return match ($x) { 1 => "a", default => "b" };', true];
        yield 'single throw body is a stub, not logic' => ['throw new \RuntimeException("");', false];

        yield 'throw after another statement is logic' => ['$x = 1; throw new \RuntimeException("");', true];
        yield 'throw expression in coalesce' => ['$x = $y ?? throw new \RuntimeException("");', true];
        yield 'try catch' => ['try {} catch (\Throwable $e) {}', true];
        yield 'ternary' => ['return $x ? "a" : "b";', true];
        yield 'nested logic inside method-call argument' => ['call_user_func(fn () => $x ? 1 : 2);', true];
    }

    private function parseMethod(string $body): ClassMethod
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse("<?php class T { public function m() { {$body} } }");
        static::assertNotNull($stmts);

        $method = (new NodeFinder())->findFirstInstanceOf($stmts, ClassMethod::class);
        static::assertInstanceOf(ClassMethod::class, $method);

        return $method;
    }
}
