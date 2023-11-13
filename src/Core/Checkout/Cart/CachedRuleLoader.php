<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
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
        return $this->cache->get(self::CACHE_KEY, fn (): RuleCollection => $this->decorated->load($context));
    }
}
