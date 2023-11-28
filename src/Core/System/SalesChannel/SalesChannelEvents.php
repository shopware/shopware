<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Event\SalesChannelIndexerEvent;

#[Package('buyers-experience')]
class SalesChannelEvents
{
    final public const SALES_CHANNEL_WRITTEN = 'sales_channel.written';

    final public const SALES_CHANNEL_DELETED = 'sales_channel.deleted';

    final public const SALES_CHANNEL_LOADED = 'sales_channel.loaded';

    final public const SALES_CHANNEL_INDEXER_EVENT = SalesChannelIndexerEvent::class;

    final public const SALES_CHANNEL_SEARCH_RESULT_LOADED = 'sales_channel.search.result.loaded';

    final public const SALES_CHANNEL_AGGREGATION_RESULT_LOADED = 'sales_channel.aggregation.result.loaded';

    final public const SALES_CHANNEL_ID_SEARCH_RESULT_LOADED = 'sales_channel.id.search.result.loaded';

    final public const SALES_CHANNEL_TRANSLATION_WRITTEN_EVENT = 'sales_channel_translation.written';

    final public const SALES_CHANNEL_TRANSLATION_DELETED_EVENT = 'sales_channel_translation.deleted';

    final public const SALES_CHANNEL_TRANSLATION_LOADED_EVENT = 'sales_channel_translation.loaded';

    final public const SALES_CHANNEL_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_translation.search.result.loaded';

    final public const SALES_CHANNEL_TRANSLATION_AGGREGATION_LOADED_EVENT = 'sales_channel_translation.aggregation.result.loaded';

    final public const SALES_CHANNEL_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_translation.id.search.result.loaded';

    final public const SALES_CHANNEL_TYPE_WRITTEN = 'sales_channel_type.written';

    final public const SALES_CHANNEL_TYPE_DELETED = 'sales_channel_type.deleted';

    final public const SALES_CHANNEL_TYPE_LOADED = 'sales_channel_type.loaded';

    final public const SALES_CHANNEL_TYPE_SEARCH_RESULT_LOADED = 'sales_channel_type.search.result.loaded';

    final public const SALES_CHANNEL_TYPE_AGGREGATION_RESULT_LOADED = 'sales_channel_type.aggregation.result.loaded';

    final public const SALES_CHANNEL_TYPE_ID_SEARCH_RESULT_LOADED = 'sales_channel_type.id.search.result.loaded';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_WRITTEN_EVENT = 'sales_channel_type_translation.written';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_DELETED_EVENT = 'sales_channel_type_translation.deleted';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_LOADED_EVENT = 'sales_channel_type_translation.loaded';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_type_translation.search.result.loaded';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'sales_channel_type_translation.aggregation.result.loaded';

    final public const SALES_CHANNEL_TYPE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'sales_channel_type_translation.id.search.result.loaded';
}
