<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Catches data sets whose entry count is incompatible with the test method's signature.
 * PHPUnit 11 silently passes the rows; PHPUnit 12 errors hard.
 *
 * Scope: same-class `#[DataProvider('providerName')]`. `#[DataProviderExternal]` requires
 * cross-class resolution and is out of scope (covered at runtime by the PHPUnit 12 preview job).
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 *
 * @deprecated tag:v6.8.0 - will be removed once PHPUnit 12 is the baseline; the runtime error then supersedes this static check.
 */
#[Package('framework')]
class DataProviderRowArityRule implements Rule
{
    private const DATA_PROVIDER_ATTRIBUTE = 'PHPUnit\\Framework\\Attributes\\DataProvider';

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $node->getClassReflection();
        if (!TestRuleHelper::isTestClass($classReflection)) {
            return [];
        }

        /** @var array<string, ClassMethod> $methods */
        $methods = [];
        foreach ($node->getOriginalNode()->stmts as $stmt) {
            if ($stmt instanceof ClassMethod) {
                $methods[(string) $stmt->name] = $stmt;
            }
        }

        $errors = [];
        foreach ($methods as $testMethodName => $testMethod) {
            // Skip non-test methods (PHPUnit only invokes test* methods; providers and helpers can carry stray DataProvider attributes without being executed as tests)
            if (!\str_starts_with($testMethodName, 'test') && !$this->hasTestAttribute($testMethod)) {
                continue;
            }
            foreach ($this->getDataProviderNames($testMethod) as $providerName) {
                if (!isset($methods[$providerName])) {
                    continue;
                }

                $providerMethod = $methods[$providerName];
                $maxParams = \count($testMethod->params);
                $testMethodName = (string) $testMethod->name;

                foreach ($this->findDataRows($providerMethod) as $row) {
                    $rowCount = $this->countTopLevelArrayItems($row);
                    if ($rowCount === null) {
                        continue;
                    }

                    if ($rowCount > $maxParams) {
                        $errors[] = RuleErrorBuilder::message(\sprintf(
                            'Data provider %s::%s yields a row with %d entries, but the consuming test %s::%s only accepts %d parameter(s). PHPUnit 12 errors hard on this.',
                            $classReflection->getName(),
                            $providerName,
                            $rowCount,
                            $classReflection->getName(),
                            $testMethodName,
                            $maxParams,
                        ))
                            ->identifier('shopware.dataProviderRowArity')
                            ->line($row->getStartLine())
                            ->build();
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function getDataProviderNames(ClassMethod $method): array
    {
        $names = [];
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $attrName = \ltrim($attr->name->toString(), '\\');
                if ($attrName !== self::DATA_PROVIDER_ATTRIBUTE && $attrName !== 'DataProvider') {
                    continue;
                }
                if (!isset($attr->args[0])) {
                    continue;
                }
                $value = $attr->args[0]->value;
                if ($value instanceof Node\Scalar\String_) {
                    $names[] = $value->value;
                }
            }
        }

        return $names;
    }

    private function hasTestAttribute(ClassMethod $method): bool
    {
        foreach ($method->attrGroups as $group) {
            foreach ($group->attrs as $attr) {
                $name = \ltrim($attr->name->toString(), '\\');
                if ($name === 'PHPUnit\\Framework\\Attributes\\Test' || $name === 'Test') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find all `[...]` array literals at the top level of yield/return statements in the provider body.
     *
     * Recognises:
     *   yield => [...];            // yield with key
     *   yield [...];               // yield without key
     *   return [[...], [...]];     // top-level return of array of rows
     *
     * @return list<Array_>
     */
    private function findDataRows(ClassMethod $method): array
    {
        if ($method->stmts === null) {
            return [];
        }

        $visitor = new class extends NodeVisitorAbstract {
            /**
             * @var list<Array_>
             */
            public array $rows = [];

            public function enterNode(Node $node): null
            {
                // yield row pattern
                if ($node instanceof Yield_ && $node->value instanceof Array_) {
                    $this->rows[] = $node->value;
                }

                // return [[row], [row], ...] pattern
                if ($node instanceof Return_ && $node->expr instanceof Array_) {
                    foreach ($node->expr->items as $item) {
                        if ($item !== null && $item->value instanceof Array_) {
                            $this->rows[] = $item->value;
                        }
                    }
                }

                return null;
            }
        };
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($method->stmts);

        return $visitor->rows;
    }

    /**
     * Count top-level entries in an array literal. Returns null if any entry is unpacked (...$spread),
     * since the count is then unknown statically.
     */
    private function countTopLevelArrayItems(Array_ $array): ?int
    {
        $count = 0;
        foreach ($array->items as $item) {
            if ($item === null) {
                continue;
            }
            if ($item->unpack) {
                return null;
            }
            ++$count;
        }

        return $count;
    }
}
