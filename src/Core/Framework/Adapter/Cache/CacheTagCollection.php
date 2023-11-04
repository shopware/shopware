<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class CacheTagCollection
{
    private array $keys = ['all' => true];

    private array $traces = [];

    public function reset(): void
    {
        $this->traces = [];
        $this->keys = ['all' => true];
    }

    public function add(string|array $tags): void
    {
        foreach (array_keys($this->keys) as $trace) {
            if (\is_string($tags)) {
                $this->traces[$trace][$tags] = true;
            }

            if (\is_array($tags)) {
                foreach ($tags as $tag) {
                    $this->traces[$trace][$tag] = true;
                }
            }
        }
    }

    /**
     * @return mixed|null All kind of data could be cached
     */
    public function trace(string $key, \Closure $param)
    {
        $this->traces[$key] = [];
        $this->keys[$key] = true;

        $result = $param();

        unset($this->keys[$key]);

        return $result;
    }

    public function getTrace(string $key): array
    {
        $trace = isset($this->traces[$key]) ? array_keys($this->traces[$key]) : [];
        unset($this->traces[$key]);

        return $trace;
    }
}
