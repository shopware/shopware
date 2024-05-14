<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class LocaleEvents
{
    final public const LOCALE_WRITTEN_EVENT = 'locale.written';

    final public const LOCALE_DELETED_EVENT = 'locale.deleted';

    final public const LOCALE_LOADED_EVENT = 'locale.loaded';

    final public const LOCALE_SEARCH_RESULT_LOADED_EVENT = 'locale.search.result.loaded';

    final public const LOCALE_AGGREGATION_LOADED_EVENT = 'locale.aggregation.result.loaded';

    final public const LOCALE_ID_SEARCH_RESULT_LOADED_EVENT = 'locale.id.search.result.loaded';

    final public const LOCALE_TRANSLATION_WRITTEN_EVENT = 'locale_translation.written';

    final public const LOCALE_TRANSLATION_DELETED_EVENT = 'locale_translation.deleted';

    final public const LOCALE_TRANSLATION_LOADED_EVENT = 'locale_translation.loaded';

    final public const LOCALE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.search.result.loaded';

    final public const LOCALE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'locale_translation.aggregation.result.loaded';

    final public const LOCALE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'locale_translation.id.search.result.loaded';
}
