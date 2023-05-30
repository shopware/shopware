<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemGoodsTotalRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemInCategoryRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemWrapperRule;
use Shopware\Core\Checkout\Customer\Rule\BillingZipCodeRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerCustomFieldRule;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Container\FilterRule;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\Framework\Rule\Container\ZipCodeRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\Rule as ShopwareRule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\TimeRangeRule;

/**
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('core')]
class RuleConditionHasRuleConfigRule implements Rule
{
    /**
     * @var list<string>
     */
    private array $rulesAllowedToBeWithoutConfig = [
        ZipCodeRule::class,
        FilterRule::class,
        Container::class,
        AndRule::class,
        NotRule::class,
        OrRule::class,
        XorRule::class,
        MatchAllLineItemsRule::class,
        ScriptRule::class,
        DateRangeRule::class,
        SimpleRule::class,
        TimeRangeRule::class,
        GoodsCountRule::class,
        GoodsPriceRule::class,
        LineItemRule::class,
        LineItemWithQuantityRule::class,
        LineItemWrapperRule::class,
        BillingZipCodeRule::class,
        ShippingZipCodeRule::class,
        AlwaysValidRule::class,
        LineItemPropertyRule::class,
        LineItemPurchasePriceRule::class,
        LineItemInCategoryRule::class,
        LineItemCustomFieldRule::class,
        LineItemGoodsTotalRule::class,
        CustomerCustomFieldRule::class,
        LineItemGroupRule::class,
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @param InClassNode $node
     *
     * @return array<array-key, RuleError|string>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$this->isRuleClass($scope) || $this->isAllowed($scope) || $this->isValid($scope)) {
            if ($this->isAllowed($scope) && $this->isValid($scope)) {
                return ['This class is implementing the getConfig function and has a own admin component. Remove getConfig or the component.'];
            }

            return [];
        }

        return ['This class has to implement getConfig or implement a new admin component.'];
    }

    private function isValid(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null || !$class->hasMethod('getConfig')) {
            return false;
        }

        $declaringClass = $class->getMethod('getConfig', $scope)->getDeclaringClass();

        return $declaringClass->getName() !== ShopwareRule::class;
    }

    private function isAllowed(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        return \in_array($class->getName(), $this->rulesAllowedToBeWithoutConfig, true);
    }

    private function isRuleClass(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }

        $namespace = $class->getName();
        if (!\str_contains($namespace, 'Shopware\\Tests\\Unit\\') && !\str_contains($namespace, 'Shopware\\Tests\\Migration\\')) {
            return false;
        }

        return $class->isSubclassOf(ShopwareRule::class);
    }
}
