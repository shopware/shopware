<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField;

use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class CustomFieldEvents
{
    final public const CUSTOM_FIELD_WRITTEN_EVENT = 'custom_field.written';

    final public const CUSTOM_FIELD_DELETED_EVENT = 'custom_field.deleted';

    final public const CUSTOM_FIELD_LOADED_EVENT = 'custom_field.loaded';

    final public const CUSTOM_FIELD_SEARCH_RESULT_LOADED_EVENT = 'custom_field.search.result.loaded';

    final public const CUSTOM_FIELD_AGGREGATION_LOADED_EVENT = 'custom_field.aggregation.result.loaded';

    final public const CUSTOM_FIELD_ID_SEARCH_RESULT_LOADED_EVENT = 'custom_field.id.search.result.loaded';

    final public const CUSTOM_FIELD_SET_WRITTEN_EVENT = 'custom_field_set.written';

    final public const CUSTOM_FIELD_SET_DELETED_EVENT = 'custom_field_set.deleted';

    final public const CUSTOM_FIELD_SET_LOADED_EVENT = 'custom_field_set.loaded';

    final public const CUSTOM_FIELD_SET_SEARCH_RESULT_LOADED_EVENT = 'custom_field_set.search.result.loaded';

    final public const CUSTOM_FIELD_SET_AGGREGATION_LOADED_EVENT = 'custom_field_set.aggregation.result.loaded';

    final public const CUSTOM_FIELD_SET_ID_SEARCH_RESULT_LOADED_EVENT = 'custom_field_set.id.search.result.loaded';

    final public const CUSTOM_FIELD_SET_RELATION_WRITTEN_EVENT = 'custom_field_set_relation.written';

    final public const CUSTOM_FIELD_SET_RELATION_DELETED_EVENT = 'custom_field_set_relation.deleted';

    final public const CUSTOM_FIELD_SET_RELATION_LOADED_EVENT = 'custom_field_set_relation.loaded';

    final public const CUSTOM_FIELD_SET_RELATION_SEARCH_RESULT_LOADED_EVENT = 'custom_field_set_relation.search.result.loaded';

    final public const CUSTOM_FIELD_SET_RELATION_AGGREGATION_LOADED_EVENT = 'custom_field_set_relation.aggregation.result.loaded';

    final public const CUSTOM_FIELD_SET_RELATION_ID_SEARCH_RESULT_LOADED_EVENT = 'custom_field_set_relation.id.search.result.loaded';
}
