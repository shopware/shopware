<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class AbstractRuleLoader
{
    abstract public function getDecorated(): AbstractRuleLoader;

    /**
     * @deprecated tag:v6.8.0 - reason:new-optional-parameter - parameter $type will be added
     */
    abstract public function load(Context $context /* , int $type = 0 */): RuleCollection;

    /**
     * @deprecated tag:v6.8.0 - reason:visibility-change - Will become abstract
     *
     * @return list<string>
     */
    public function loadIds(Context $context, int $type = 0): array
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', 'AbstractRuleLoader::loadIds() is deprecated and will become abstract in v6.8.0.0. Please implement it in your incrementer class.');

        return $this->getDecorated()->loadIds($context, $type);
    }
}
