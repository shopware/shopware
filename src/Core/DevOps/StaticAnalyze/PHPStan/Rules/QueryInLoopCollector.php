<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\Type;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects what {@see NoIndirectQueryInLoopRule} needs to see a query that a loop reaches through another method,
 * which a rule working on a single call cannot know.
 *
 * Three kinds of facts are collected:
 *
 * - `query`: the method holding this call hits the database.
 * - `call`: the method holding this call delegates to another method, of its own class or of the receiver's class.
 * - `looped`: the same delegation, made from a loop that scales with the number of records.
 *
 * @phpstan-type QueryInLoopFact array{kind: 'query'|'call'|'looped', caller: string, target: string, line: int, method: string}
 *
 * @phpstan-import-type LoopContext from LoopContextVisitor
 *
 * @implements Collector<MethodCall, list<QueryInLoopFact>>
 *
 * @internal
 */
#[Package('framework')]
class QueryInLoopCollector implements Collector
{
    use InTestClassTrait;

    public function __construct(private readonly QueryCallDetector $detector)
    {
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<QueryInLoopFact>|null
     */
    public function processNode(Node $node, Scope $scope): ?array
    {
        if (!$node instanceof MethodCall || !$node->name instanceof Identifier) {
            return null;
        }

        if ($this->isInTestClass($scope) || $this->detector->isExcludedClass($scope)) {
            return null;
        }

        $classReflection = $scope->getClassReflection();
        $function = $scope->getFunctionName();

        if ($classReflection === null || $function === null) {
            return null;
        }

        $class = $classReflection->getName();
        $caller = $class . '::' . $function;
        $method = $node->name->toString();
        $calledOn = $scope->getType($node->var);
        $facts = [];

        if ($this->detector->getQueryClass($method, $calledOn) !== null) {
            $facts[] = ['kind' => 'query', 'caller' => $caller, 'target' => $caller, 'line' => $node->getStartLine(), 'method' => $method];
        }

        // A call site typed against a parent or an interface names that declaration, while the query sits in the
        // implementation, so the declaration is recorded as delegating to it.
        foreach ($classReflection->getAncestors() as $ancestor) {
            if ($ancestor->getName() === $class || !$ancestor->hasMethod($function)) {
                continue;
            }

            $facts[] = ['kind' => 'call', 'caller' => $ancestor->getName() . '::' . $function, 'target' => $caller, 'line' => $node->getStartLine(), 'method' => $function];
        }

        /** @var list<LoopContext> $loops */
        $loops = $node->getAttribute(LoopContextVisitor::ATTRIBUTE, []);
        $looped = $loops !== [] && $this->detector->scalesWithRecords($loops, $scope);

        foreach ($this->targetsOf($node, $calledOn, $class, $method) as $target) {
            $facts[] = ['kind' => 'call', 'caller' => $caller, 'target' => $target, 'line' => $node->getStartLine(), 'method' => $method];

            if ($looped) {
                $facts[] = ['kind' => 'looped', 'caller' => $caller, 'target' => $target, 'line' => $node->getStartLine(), 'method' => $method];
            }
        }

        return $facts === [] ? null : $facts;
    }

    /**
     * The methods this call can reach: the same class for `$this->helper()`, otherwise the receiver's own type. Only
     * Shopware classes are followed, because a query is never reached through a vendor class we do not analyse.
     *
     * @return list<string>
     */
    private function targetsOf(MethodCall $node, Type $calledOn, string $class, string $method): array
    {
        if ($node->var instanceof Variable && $node->var->name === 'this') {
            return [$class . '::' . $method];
        }

        $targets = [];

        foreach ($calledOn->getObjectClassNames() as $name) {
            if (str_starts_with($name, 'Shopware\\')) {
                $targets[] = $name . '::' . $method;
            }
        }

        return $targets;
    }
}
