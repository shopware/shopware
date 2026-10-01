<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * Forbids combining a `#[Depends*]` attribute with a `#[DataProvider*]` attribute on the same test method.
 *
 * The combination is a footgun. PHPUnit appends the dependency's return value to the data-provider
 * arguments before invoking the test. With positional provider rows it happens to work, but the
 * moment a row uses named (associative) keys PHPUnit fails hard with
 * "Cannot use positional argument after named argument during unpacking"
 * (sebastianbergmann/phpunit#5827, #6104). The safe-vs-unsafe line depends on the row shape — and is
 * invisible for providers that build rows dynamically (e.g. yielding from a fixture file) — so rather
 * than guess at row shapes the combination is banned outright.
 *
 * Note: the #6104 fix (PHPUnit 11.5.26, via #6251) only suppresses this for dependencies that return
 * void/never — those no longer append a value. A value-returning dependency (e.g. a testIndexing that
 * returns an IdsCollection) combined with named-key provider rows still fails hard on current PHPUnit,
 * which is exactly the shape this rule targets.
 *
 * Pass the dependency's data through a static property populated by the depended-upon test (or by
 * setUpBeforeClass) instead of through `#[Depends]`.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoDependsWithDataProviderRule implements Rule
{
    private const DEPENDS_ATTRIBUTES = [
        'Depends',
        'DependsExternal',
        'DependsOnClass',
        'DependsUsingDeepClone',
        'DependsUsingShallowClone',
        'DependsExternalUsingDeepClone',
        'DependsExternalUsingShallowClone',
        'DependsOnClassUsingDeepClone',
        'DependsOnClassUsingShallowClone',
    ];

    private const DATA_PROVIDER_ATTRIBUTES = [
        'DataProvider',
        'DataProviderExternal',
    ];

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

        $errors = [];
        foreach ($node->getOriginalNode()->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod) {
                continue;
            }

            $hasDepends = false;
            $dataProviderLine = null;
            foreach ($stmt->attrGroups as $group) {
                foreach ($group->attrs as $attr) {
                    $name = $this->shortAttributeName($attr->name->toString());
                    if (\in_array($name, self::DEPENDS_ATTRIBUTES, true)) {
                        $hasDepends = true;
                    }
                    if (\in_array($name, self::DATA_PROVIDER_ATTRIBUTES, true)) {
                        $dataProviderLine = $attr->getStartLine();
                    }
                }
            }

            if (!$hasDepends || $dataProviderLine === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'Test %s::%s combines #[Depends] with #[DataProvider]. PHPUnit appends the dependency return value to the provided arguments, which breaks hard ("Cannot use positional argument after named argument during unpacking") as soon as a provider row uses named keys. Pass the dependency data via a static property set in the depended-upon test (or setUpBeforeClass) instead.',
                $classReflection->getName(),
                (string) $stmt->name,
            ))
                ->identifier('shopware.noDependsWithDataProvider')
                ->line($dataProviderLine)
                ->build();
        }

        return $errors;
    }

    private function shortAttributeName(string $name): string
    {
        $name = \ltrim($name, '\\');
        $pos = \strrpos($name, '\\');

        return $pos === false ? $name : \substr($name, $pos + 1);
    }
}
