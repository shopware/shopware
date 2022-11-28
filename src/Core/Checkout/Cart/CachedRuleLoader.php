<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @package checkout
 */
class CachedRuleLoader extends AbstractRuleLoader
{
    public const CACHE_KEY = 'cart_rules';

    private AbstractRuleLoader $decorated;

    private CacheInterface $cache;

    /**
     * @internal
     */
    public function __construct(AbstractRuleLoader $decorated, CacheInterface $cache)
    {
        $this->decorated = $decorated;
        $this->cache = $cache;
    }

    public function getDecorated(): AbstractRuleLoader
    {
        return $this->decorated;
    }

    public function load(Context $context): RuleCollection
    {
        return $this->cache->get(self::CACHE_KEY, function () use ($context): RuleCollection {
            return $this->decorated->load($context);
        });
    }
}
