<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Definitions, entities and collections are constructed by the DAL before removal without emitting notices.
 * Require an explicit constructor so direct construction, including empty collections, fails in major mode.
 * Service removal is checked separately by DeprecatedServiceFeatureTagRule; method metadata policies
 * live in DeprecatedFrameworkMethodPattern. This checks the first statement, not arbitrary control flow.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedEntityConstructionRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $class = $node->getClassReflection();
        $deprecation = $class->getDeprecatedDescription();
        if ($class->isAbstract() || $deprecation === null || (!$class->is(EntityDefinition::class) && !$class->is(EntityCollection::class) && !$class->is(Entity::class))) {
            return [];
        }

        if (!preg_match('/tag:(v\d+\.\d+\.\d+)/', $deprecation, $matches)) {
            return [];
        }

        $flag = $matches[1] . '.0';
        $constructor = $node->getOriginalNode()->getMethod('__construct');
        $first = $constructor?->stmts[0] ?? null;
        if ($first instanceof Expression && $first->expr instanceof StaticCall) {
            $call = $first->expr;
            $argument = $call->args[0] ?? null;
            if ($call->class instanceof Node\Name && $scope->resolveName($call->class) === Feature::class
                && $call->name instanceof Identifier && $call->name->toString() === 'throwIfActive'
                && $argument instanceof Arg && $argument->value instanceof String_ && $argument->value->value === $flag) {
                return [];
            }
        }

        return [RuleErrorBuilder::message(\sprintf('Deprecated DAL class "%s" must declare a constructor starting with "Feature::throwIfActive" for feature flag "%s".', $class->getName(), $flag))
            ->identifier('shopware.deprecatedEntityConstruction')
            ->build()];
    }
}
