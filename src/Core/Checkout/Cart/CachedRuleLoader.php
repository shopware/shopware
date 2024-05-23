<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
 *
 * @deprecated tag:v6.7.0 - Will be removed, use \Shopware\Core\Content\Rule\RuleLoader
 */
#[Package('checkout')]
class CachedRuleLoader extends AbstractRuleLoader
{
    final public const CACHE_KEY = 'cart_rules';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRuleLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this->decorated;
    }

    public function load(Context $context): RuleCollection
    {
        if (Feature::isActive('cache_rework')) {
            // @deprecated tag:v6.7.0 - remove cache layer
            return $this->decorated->load($context);
        }

        // todo@skroblin deprecate + in flow use plain sql solution like in cart
        // todo@skroblin upgrade guide
        return $this->cache->get(self::CACHE_KEY, fn (): RuleCollection => $this->decorated->load($context));
    }
}
