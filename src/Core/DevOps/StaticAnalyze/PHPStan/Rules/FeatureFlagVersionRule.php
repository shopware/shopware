<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * Version-shaped feature flags have four parts (for example v6.8.0.0), while deprecation
 * labels use three (v6.8.0). A malformed version flag silently misses the registered feature
 * and can make a runtime deprecation check throw early. Check constant flag arguments at call sites.
 *
 * @implements Rule<StaticCall>
 *
 * @internal
 */
#[Package('framework')]
class FeatureFlagVersionRule implements Rule
{
    private const FLAG_METHODS = [
        'withFeatureEnabled',
        'withFeatureDisabled',
        'isActive',
        'ifActive',
        'setActive',
        'ifNotActive',
        'callSilentIfInactive',
        'silent',
        'skipTestIfInActive',
        'skipTestIfActive',
        'throwException',
        'triggerDeprecationOrThrow',
        'has',
        'registerFeature',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name || $scope->resolveName($node->class) !== Feature::class || !$node->name instanceof Identifier) {
            return [];
        }

        $method = $node->name->toString();
        if (!\in_array($method, self::FLAG_METHODS, true)) {
            return [];
        }

        $errors = [];
        foreach ($node->getArgs() as $index => $argument) {
            if ($argument->name !== null) {
                if (!\in_array($argument->name->name, ['feature', 'flagName', 'flag', 'majorFlag', 'silentUntil'], true)) {
                    continue;
                }
            } elseif ($index !== 0 && !($method === 'triggerDeprecationOrThrow' && $index === 3)) {
                continue;
            }

            foreach ($scope->getType($argument->value)->getConstantStrings() as $flag) {
                $name = $flag->getValue();
                // Accept names that do not start with v and a digit, and version flags with exactly four numeric parts.
                if (\preg_match('/\Av\d/i', $name) !== 1 || \preg_match('/\Av\d+(?:[._]\d+){3}\z/i', $name) === 1) {
                    continue;
                }

                $errors[] = RuleErrorBuilder::message(\sprintf(
                    'Version-shaped feature flag "%s" must have four numeric parts (for example "v6.8.0.0").',
                    $name,
                ))
                    ->identifier('shopware.featureFlagVersion')
                    ->line($argument->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }
}
