<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property;

use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class PropertyEvents
{
    final public const PROPERTY_GROUP_WRITTEN_EVENT = 'property_group.written';

    final public const PROPERTY_GROUP_DELETED_EVENT = 'property_group.deleted';

    final public const PROPERTY_GROUP_LOADED_EVENT = 'property_group.loaded';

    final public const PROPERTY_GROUP_SEARCH_RESULT_LOADED_EVENT = 'property_group.search.result.loaded';

    final public const PROPERTY_GROUP_AGGREGATION_LOADED_EVENT = 'property_group.aggregation.result.loaded';

    final public const PROPERTY_GROUP_ID_SEARCH_RESULT_LOADED_EVENT = 'property_group.id.search.result.loaded';

    final public const PROPERTY_GROUP_OPTION_WRITTEN_EVENT = 'property_group_option.written';

    final public const PROPERTY_GROUP_OPTION_DELETED_EVENT = 'property_group_option.deleted';

    final public const PROPERTY_GROUP_OPTION_LOADED_EVENT = 'property_group_option.loaded';

    final public const PROPERTY_GROUP_OPTION_SEARCH_RESULT_LOADED_EVENT = 'property_group_option.search.result.loaded';

    final public const PROPERTY_GROUP_OPTION_AGGREGATION_LOADED_EVENT = 'property_group_option.aggregation.result.loaded';

    final public const PROPERTY_GROUP_OPTION_ID_SEARCH_RESULT_LOADED_EVENT = 'property_group_option.id.search.result.loaded';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_WRITTEN_EVENT = 'property_group_option_translation.written';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_DELETED_EVENT = 'property_group_option_translation.deleted';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_LOADED_EVENT = 'property_group_option_translation.loaded';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'property_group_option_translation.search.result.loaded';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_AGGREGATION_LOADED_EVENT = 'property_group_option_translation.aggregation.result.loaded';

    final public const PROPERTY_GROUP_OPTION_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'property_group_option_translation.id.search.result.loaded';

    final public const PROPERTY_GROUP_TRANSLATION_WRITTEN_EVENT = 'property_group_translation.written';

    final public const PROPERTY_GROUP_TRANSLATION_DELETED_EVENT = 'property_group_translation.deleted';

    final public const PROPERTY_GROUP_TRANSLATION_LOADED_EVENT = 'property_group_translation.loaded';

    final public const PROPERTY_GROUP_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'property_group_translation.search.result.loaded';

    final public const PROPERTY_GROUP_TRANSLATION_AGGREGATION_LOADED_EVENT = 'property_group_translation.aggregation.result.loaded';

    final public const PROPERTY_GROUP_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'property_group_translation.id.search.result.loaded';
}
