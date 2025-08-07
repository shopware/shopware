<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\PluginMapping;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
readonly class PluginMapping
{
    public function __construct(
        public string $pluginName,
        public string $snippetName,
    ) {
    }
}
