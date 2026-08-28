<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore\LogicDetector;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
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

        yield 'single local write then return' => ['$values = $this->load(); return $values;', false];
        yield 'two different locals written once each' => ['$a = 1; $b = $a; return $b;', false];
        yield 'local built from a literal in one write' => ['$config = ["a" => 1, "b" => 2]; return json_encode($config);', false];
        yield 'destructuring into fresh locals' => ['[$a, $b] = $this->pair(); return $a . $b;', false];
        yield 'write inside a closure does not count against the outer local' => ['$x = 1; $fn = static function () { $x = 2; return $x; }; return $fn();', false];
        yield 'write inside an arrow function does not count against the outer local' => ['$x = 1; $fn = static fn () => $x = 2; return $fn();', false];

        yield 'unset on a local offset' => ['$data = $this->load(); unset($data["extensions"]); return $data;', true];
        yield 'unset on a property offset' => ['unset($this->payload[$key]);', true];
        yield 'compound array union' => ['$values = $this->attributes(); $values += $this->children(); return $values;', true];
        yield 'compound string concat' => ['$sql .= " AND deleted = 0";', true];
        yield 'compound arithmetic on a property' => ['$this->count += 1;', true];
        yield 'null-coalescing assignment' => ['$token ??= $this->generate();', true];
        yield 'increment' => ['$this->position++;', true];
        yield 'local reassigned as a whole' => ['$values = $this->load(); $values = array_merge($values, $this->more()); return $values;', true];
        yield 'local written then extended through an offset' => ['$data = []; $data["x"] = $this->x; return $data;', true];
        yield 'local written then extended through a property' => ['$dto = new \stdClass(); $dto->x = 1; return $dto;', true];
        yield 'parameter reassigned' => ['$name = trim($name); return $name;', true];
        yield 'destructuring over an existing local' => ['$a = 1; [$a, $b] = $this->pair(); return $a . $b;', true];

        yield 'discarded call on $this is the class acting for effect' => ['$this->checkIfPropertyAccessIsAllowed("password"); return $this->password;', true];
        yield 'discarded nullsafe call on $this' => ['$this?->refresh();', true];
        yield 'discarded self:: call' => ['self::validate($name);', true];
        yield 'discarded static:: call' => ['static::boot();', true];
        yield 'discarded call on a collaborator is delegation' => ['$this->dep->call();', false];
        yield 'discarded parent:: call chains to the parent' => ['parent::boot();', false];
        yield 'discarded call on another class' => ['\\Shopware\\Core\\Framework\\Feature::triggerDeprecationOrThrow("v6.8.0.0", "msg");', false];
        yield 'own call whose result is returned is a delegating getter' => ['return $this->compute();', false];
        yield 'own call whose result is assigned once' => ['$value = $this->compute(); return $value;', false];
    }

    #[TestDox('in a Throwable context, methodContainsLogic($_dataName)')]
    #[DataProvider('throwableContextProvider')]
    public function testMethodContainsLogicInThrowableContext(string $body, bool $expected): void
    {
        static::assertSame($expected, LogicDetector::methodContainsLogic($this->parseMethod($body), true));
    }

    /**
     * @return \Generator<string, array{0: string, 1: bool}>
     */
    public static function throwableContextProvider(): \Generator
    {
        yield 'feature-flag fork between two exceptions is not logic' => [
            'if (!$flag) { return new \RuntimeException(""); } return new \LogicException("");',
            false,
        ];
        yield 'message-variant if/else is not logic' => [
            'if ($code === null) { $m = "a"; } else { $m = "b"; } return new \RuntimeException($m);',
            false,
        ];
        yield 'ternary is not logic' => ['return $x ? "a" : "b";', false];
        yield 'foreach aggregation is still logic' => ['foreach ([] as $i) {}', true];
        yield 'try catch is still logic' => ['try {} catch (\Throwable $e) {}', true];
        yield 'match is still logic' => ['return match ($x) { 1 => "a", default => "b" };', true];
        yield 'multi-statement throw is still logic' => ['$x = 1; throw new \RuntimeException("");', true];
        yield 'compound assignment is still logic' => ['$m = "a"; $m .= "b"; return new \RuntimeException($m);', true];
        yield 'unset is still logic' => ['unset($params["secret"]); return new \RuntimeException("");', true];
        yield 'discarded own call is still logic' => ['$this->log(); return new \RuntimeException("");', true];
    }

    private function parseMethod(string $body): ClassMethod
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = $parser->parse("<?php class T { public function m(string \$name = '', string \$key = '') { {$body} } }");
        static::assertNotNull($stmts);

        $method = (new NodeFinder())->findFirstInstanceOf($stmts, ClassMethod::class);
        static::assertInstanceOf(ClassMethod::class, $method);

        return $method;
    }
}
