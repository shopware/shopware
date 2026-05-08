<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @final Depend on the AbstractRuleLoader which is the definition of public API for this scope
 */
#[Package('checkout')]
class CachedRuleLoader extends AbstractRuleLoader
{
    final public const CACHE_KEY = 'cart_rules';
    final public const CACHE_IDS_KEY = 'cart_rule_ids_';

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

    public function load(Context $context /* , int $type = 0 */): RuleCollection
    {
        $key = self::CACHE_KEY;
        $type = 0;

        if (\func_num_args() >= 2) {
            $type = \func_get_arg(1);
            $key .= '_' . $type;

            $source = $context->getSource();
            if ($source instanceof SalesChannelApiSource) {
                $key .= '_' . $source->getSalesChannelId();
            }
        }

        return CacheValueCompressor::uncompress($this->cache->get($key, function (ItemInterface $item) use ($context, $type): string {
            $item->tag(self::CACHE_KEY);

            // @phpstan-ignore-next-line arguments.count
            return CacheValueCompressor::compress($this->decorated->load($context, $type));
        }));
    }

    public function loadIds(Context $context, int $type = 0): array
    {
        $key = self::CACHE_IDS_KEY . $type;

        $source = $context->getSource();
        if ($source instanceof SalesChannelApiSource) {
            $key .= '_' . $source->getSalesChannelId();
        }

        return CacheValueCompressor::uncompress($this->cache->get($key, function (ItemInterface $item) use ($context, $type): string {
            $item->tag(self::CACHE_KEY);

            return CacheValueCompressor::compress($this->decorated->loadIds($context, $type));
        }));
    }
}
