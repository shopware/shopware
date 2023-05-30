<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('merchant-services')]
class PluginRegionStruct extends Struct
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var PluginCategoryCollection
     */
    protected $categories;

    public function __construct(
        string $name,
        string $label,
        iterable $categories
    ) {
        $this->name = $name;
        $this->label = $label;
        $this->categories = new PluginCategoryCollection($categories);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategories(): PluginCategoryCollection
    {
        return $this->categories;
    }

    public function getApiAlias(): string
    {
        return 'store_plugin_region';
    }
}
