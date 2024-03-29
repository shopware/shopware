<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class PluginEvents
{
    final public const PLUGIN_WRITTEN_EVENT = 'plugin.written';

    final public const PLUGIN_DELETED_EVENT = 'plugin.deleted';

    final public const PLUGIN_LOADED_EVENT = 'plugin.loaded';

    final public const PLUGIN_SEARCH_RESULT_LOADED_EVENT = 'plugin.search.result.loaded';

    final public const PLUGIN_AGGREGATION_LOADED_EVENT = 'plugin.aggregation.result.loaded';

    final public const PLUGIN_ID_SEARCH_RESULT_LOADED_EVENT = 'plugin.id.search.result.loaded';
}
