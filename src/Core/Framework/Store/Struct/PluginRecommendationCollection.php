<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @codeCoverageIgnore
 * Pseudo immutable collection
 *
 * @method StorePluginStruct[]    getIterator()
 * @method StorePluginStruct[]    getElements()
 * @method StorePluginStruct|null get(string $key)
 * @method StorePluginStruct|null first()
 * @method StorePluginStruct|null last()
 */
final class PluginRecommendationCollection extends Collection
{
    public function __construct(iterable $elements = [])
    {
        parent::__construct();

        $this->elements = [];
        foreach ($elements as $element) {
            $this->validateType($element);
            $this->elements[] = $element;
        }
    }

    public function getExpectedClass(): string
    {
        return StorePluginStruct::class;
    }

    public function add($element): void
    {
        // disallow add
    }

    public function set($key, $element): void
    {
        // disallow set
    }

    public function sort(\Closure $closure): void
    {
        // disallow sorting
    }

    public function getApiAlias(): string
    {
        return 'store_plugin_recommendation_collection';
    }
}
