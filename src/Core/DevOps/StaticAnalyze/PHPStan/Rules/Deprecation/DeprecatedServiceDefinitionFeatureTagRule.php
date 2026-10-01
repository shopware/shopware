<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Expression;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

/**
 * Checks deprecations declared in PHP service configuration, where the class may not be deprecated.
 * Reads ServicesConfigurator set()/alias() chains, adjacent deprecation comments, and deprecate()
 * calls. Deprecated set() registrations need a matching shopware.inactiveFeature tag; annotated
 * aliases need a removal-map entry because Symfony aliases cannot be tagged.
 *
 * @implements Rule<Expression>
 *
 * @internal
 */
#[Package('framework')]
class DeprecatedServiceDefinitionFeatureTagRule implements Rule
{
    public function getNodeType(): string
    {
        return Expression::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Expression || !$node->expr instanceof MethodCall) {
            return [];
        }

        $methods = [];
        $call = $node->expr;

        while ($call instanceof MethodCall) {
            if (!$call->name instanceof Identifier) {
                return [];
            }

            $methods[] = ['call' => $call, 'name' => $call->name->toString()];
            $call = $call->var;
        }

        if (!$call instanceof Variable || !(new ObjectType(ServicesConfigurator::class))->isSuperTypeOf($scope->getType($call))->yes()) {
            return [];
        }

        $methods = array_reverse($methods);
        $commentFlag = null;
        foreach ($node->getComments() as $comment) {
            if ($comment->getEndLine() !== $node->getStartLine() - 1) {
                continue;
            }

            if (preg_match('/@deprecated\s+tag:(v\d+\.\d+\.\d+(?:\.\d+)?)/', $comment->getText(), $matches)) {
                $commentFlag = $matches[1];
            }
        }

        if ($commentFlag !== null && substr_count($commentFlag, '.') === 2) {
            $commentFlag .= '.0';
        }

        if ($methods[0]['name'] === 'alias') {
            if ($commentFlag === null) {
                return [];
            }

            $aliasArgument = $methods[0]['call']->getArgs()[0]->value ?? null;
            $aliasIds = $aliasArgument === null ? [] : $scope->getType($aliasArgument)->getConstantStrings();
            if (\count($aliasIds) !== 1) {
                return [RuleErrorBuilder::message('Deprecated service aliases must have a constant ID to check the removal flag.')
                    ->identifier('shopware.deprecatedServiceAliasFeatureFlag')
                    ->build()];
            }

            $aliasId = $aliasIds[0]->getValue();
            if (\in_array($aliasId, FeatureFlagCompilerPass::ALIASES_TO_REMOVE[$commentFlag] ?? [], true)) {
                return [];
            }

            return [RuleErrorBuilder::message(\sprintf('Deprecated service alias "%s" scheduled for "%s" must be listed in FeatureFlagCompilerPass::ALIASES_TO_REMOVE.', $aliasId, $commentFlag))
                ->identifier('shopware.deprecatedServiceAliasFeatureFlag')
                ->build()];
        }

        if ($methods[0]['name'] !== 'set') {
            return [];
        }

        $deprecation = null;
        $tagFlags = [];

        foreach ($methods as $method) {
            if ($method['name'] === 'deprecate') {
                $deprecation = $method['call'];
            }

            $tagName = $method['call']->getArgs()[0]->value ?? null;
            if ($method['name'] !== 'tag' || !$tagName instanceof String_ || $tagName->value !== 'shopware.inactiveFeature') {
                continue;
            }

            $attributes = $method['call']->getArgs()[1]->value ?? null;
            if (!$attributes instanceof Array_) {
                continue;
            }

            foreach ($attributes->items as $item) {
                if ($item?->key instanceof String_ && $item->key->value === 'flag' && $item->value instanceof String_) {
                    $tagFlags[] = $item->value->value;
                }
            }
        }

        $description = $deprecation?->getArgs()[2]->value ?? null;
        $flag = null;

        if ($description instanceof String_ && preg_match('/\bv(\d+\.\d+\.\d+(?:\.\d+)?)\b/', $description->value, $matches)) {
            $flag = 'v' . $matches[1];
        }

        $flag ??= $commentFlag;

        if ($deprecation === null && $flag === null) {
            return [];
        }

        if ($flag !== null && substr_count($flag, '.') === 2) {
            $flag .= '.0';
        }

        if ($flag !== null ? \in_array($flag, $tagFlags, true) : $tagFlags !== []) {
            return [];
        }

        return [RuleErrorBuilder::message($flag === null
            ? 'Deprecated service definitions must be tagged "shopware.inactiveFeature".'
            : \sprintf('Deprecated service definitions scheduled for "%s" must be tagged "shopware.inactiveFeature" with that flag.', $flag))
            ->identifier('shopware.deprecatedServiceDefinitionFeatureTag')
            ->build()];
    }
}
