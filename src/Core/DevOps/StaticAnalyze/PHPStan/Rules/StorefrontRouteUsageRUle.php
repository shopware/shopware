<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<String_>
 *
 * @internal
 */
#[Package('framework')]
class StorefrontRouteUsageRUle implements Rule
{
    public function getNodeType(): string
    {
        return String_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $scopeClassReflection = $scope->getClassReflection();
        if (!$scopeClassReflection || TestRuleHelper::isTestClass($scopeClassReflection)) {
            return [];
        }

        $namespace = $scope->getNamespace();
        if ($namespace === null || str_starts_with($namespace, 'Shopware\\Storefront')) {
            return [];
        }

        $value = $node->value;
        /** @phpstan-ignore shopware.storefrontRouteUsage (As the PHPStan rule checks itself, this needs to be ignored) */
        if (str_starts_with($value, 'frontend.')) {
            return [
                RuleErrorBuilder::message(\sprintf('Using a route name starting with "frontend." is not allowed in the Shopware\Core namespace (found: "%s").', $value))
                    ->line($node->getStartLine())
                    ->identifier('shopware.storefrontRouteUsage')
                    ->tip('Routes starting with "frontend." are provided by the Storefront package, which is not always installed.')
                    ->build(),
            ];
        }

        return [];
    }
}
