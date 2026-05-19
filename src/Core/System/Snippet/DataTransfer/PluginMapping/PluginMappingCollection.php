<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\PluginMapping;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<PluginMapping>
 */
#[Package('discovery')]
class PluginMappingCollection extends Collection
{
    public function add($element): void
    {
        $this->set($element->pluginName, $element);
    }

    public function set($key, $element): void
    {
        parent::set($element->pluginName, $element);
    }

    protected function getExpectedClass(): string
    {
        return PluginMapping::class;
    }
}
