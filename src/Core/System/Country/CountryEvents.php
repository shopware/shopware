<?php declare(strict_types=1);

namespace Shopware\Core\System\Country;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CountryEvents
{
    final public const COUNTRY_WRITTEN_EVENT = 'country.written';

    final public const COUNTRY_DELETED_EVENT = 'country.deleted';

    final public const COUNTRY_LOADED_EVENT = 'country.loaded';

    final public const COUNTRY_SEARCH_RESULT_LOADED_EVENT = 'country.search.result.loaded';

    final public const COUNTRY_AGGREGATION_LOADED_EVENT = 'country.aggregation.result.loaded';

    final public const COUNTRY_ID_SEARCH_RESULT_LOADED_EVENT = 'country.id.search.result.loaded';

    final public const COUNTRY_AREA_WRITTEN_EVENT = 'country_area.written';

    final public const COUNTRY_AREA_DELETED_EVENT = 'country_area.deleted';

    final public const COUNTRY_AREA_LOADED_EVENT = 'country_area.loaded';

    final public const COUNTRY_AREA_SEARCH_RESULT_LOADED_EVENT = 'country_area.search.result.loaded';

    final public const COUNTRY_AREA_AGGREGATION_LOADED_EVENT = 'country_area.aggregation.result.loaded';

    final public const COUNTRY_AREA_ID_SEARCH_RESULT_LOADED_EVENT = 'country_area.id.search.result.loaded';

    final public const COUNTRY_AREA_TRANSLATION_WRITTEN_EVENT = 'country_area_translation.written';

    final public const COUNTRY_AREA_TRANSLATION_DELETED_EVENT = 'country_area_translation.deleted';

    final public const COUNTRY_AREA_TRANSLATION_LOADED_EVENT = 'country_area_translation.loaded';

    final public const COUNTRY_AREA_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'country_area_translation.search.result.loaded';

    final public const COUNTRY_AREA_TRANSLATION_AGGREGATION_LOADED_EVENT = 'country_area_translation.aggregation.result.loaded';

    final public const COUNTRY_AREA_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'country_area_translation.id.search.result.loaded';

    final public const COUNTRY_STATE_WRITTEN_EVENT = 'country_state.written';

    final public const COUNTRY_STATE_DELETED_EVENT = 'country_state.deleted';

    final public const COUNTRY_STATE_LOADED_EVENT = 'country_state.loaded';

    final public const COUNTRY_STATE_SEARCH_RESULT_LOADED_EVENT = 'country_state.search.result.loaded';

    final public const COUNTRY_STATE_AGGREGATION_LOADED_EVENT = 'country_state.aggregation.result.loaded';

    final public const COUNTRY_STATE_ID_SEARCH_RESULT_LOADED_EVENT = 'country_state.id.search.result.loaded';

    final public const COUNTRY_STATE_TRANSLATION_WRITTEN_EVENT = 'country_state_translation.written';

    final public const COUNTRY_STATE_TRANSLATION_DELETED_EVENT = 'country_state_translation.deleted';

    final public const COUNTRY_STATE_TRANSLATION_LOADED_EVENT = 'country_state_translation.loaded';

    final public const COUNTRY_STATE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'country_state_translation.search.result.loaded';

    final public const COUNTRY_STATE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'country_state_translation.aggregation.result.loaded';

    final public const COUNTRY_STATE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'country_state_translation.id.search.result.loaded';

    final public const COUNTRY_TRANSLATION_WRITTEN_EVENT = 'country_translation.written';

    final public const COUNTRY_TRANSLATION_DELETED_EVENT = 'country_translation.deleted';

    final public const COUNTRY_TRANSLATION_LOADED_EVENT = 'country_translation.loaded';

    final public const COUNTRY_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'country_translation.search.result.loaded';

    final public const COUNTRY_TRANSLATION_AGGREGATION_LOADED_EVENT = 'country_translation.aggregation.result.loaded';

    final public const COUNTRY_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'country_translation.id.search.result.loaded';
}
