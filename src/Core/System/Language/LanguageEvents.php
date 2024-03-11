<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class LanguageEvents
{
    final public const LANGUAGE_WRITTEN_EVENT = 'language.written';

    final public const LANGUAGE_DELETED_EVENT = 'language.deleted';

    final public const LANGUAGE_LOADED_EVENT = 'language.loaded';

    final public const LANGUAGE_SEARCH_RESULT_LOADED_EVENT = 'language.search.result.loaded';

    final public const LANGUAGE_AGGREGATION_LOADED_EVENT = 'language.aggregation.result.loaded';

    final public const LANGUAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'language.id.search.result.loaded';
}
