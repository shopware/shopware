<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @extends AbstractCacheTracer<mixed|null>
 */
class CacheTracer extends AbstractCacheTracer
{
    private SystemConfigService $config;

    private AbstractTranslator $translator;

    private CacheTagCollection $collection;

    public function __construct(SystemConfigService $config, AbstractTranslator $translator, CacheTagCollection $collection)
    {
        $this->config = $config;
        $this->translator = $translator;
        $this->collection = $collection;
    }

    public function getDecorated(): AbstractCacheTracer
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @return mixed|null All kind of data could be cached
     */
    public function trace(string $key, \Closure $param)
    {
        return $this->collection->trace($key, function () use ($key, $param) {
            return $this->translator->trace($key, function () use ($key, $param) {
                return $this->config->trace($key, $param);
            });
        });
    }

    public function get(string $key): array
    {
        return array_merge(
            $this->collection->getTrace($key),
            $this->config->getTrace($key),
            $this->translator->getTrace($key)
        );
    }
}
