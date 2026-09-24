<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects what {@see NoIndirectQueryInLoopRule} needs to see a query that a loop reaches through a helper method of
 * the same class, which a rule working on a single call cannot know.
 *
 * Three kinds of facts are collected:
 *
 * - `query`: the method holding this call hits the database.
 * - `call`: the method holding this call delegates to another method of the same class.
 * - `looped`: the method holding this call delegates to another method of the same class from a loop that scales with
 *   the number of records.
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

        $class = $scope->getClassReflection()?->getName();
        $function = $scope->getFunctionName();

        if ($class === null || $function === null) {
            return null;
        }

        $caller = $class . '::' . $function;
        $method = $node->name->toString();
        $facts = [];

        if ($this->detector->getQueryClass($method, $scope->getType($node->var)) !== null) {
            $facts[] = ['kind' => 'query', 'caller' => $caller, 'target' => $caller, 'line' => $node->getStartLine(), 'method' => $method];
        }

        // only delegation within the same class is followed: a helper lives next to its caller, while following any
        // receiver would need a call graph over interfaces and inheritance
        if (!$node->var instanceof Variable || $node->var->name !== 'this') {
            return $facts === [] ? null : $facts;
        }

        $target = $class . '::' . $method;

        $facts[] = ['kind' => 'call', 'caller' => $caller, 'target' => $target, 'line' => $node->getStartLine(), 'method' => $method];

        /** @var list<LoopContext> $loops */
        $loops = $node->getAttribute(LoopContextVisitor::ATTRIBUTE, []);

        if ($loops !== [] && $this->detector->scalesWithRecords($loops, $scope)) {
            $facts[] = ['kind' => 'looped', 'caller' => $caller, 'target' => $target, 'line' => $node->getStartLine(), 'method' => $method];
        }

        return $facts;
    }
}
