<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CurrencyEvents
{
    final public const CURRENCY_WRITTEN_EVENT = 'currency.written';

    final public const CURRENCY_DELETED_EVENT = 'currency.deleted';

    final public const CURRENCY_LOADED_EVENT = 'currency.loaded';

    final public const CURRENCY_SEARCH_RESULT_LOADED_EVENT = 'currency.search.result.loaded';

    final public const CURRENCY_AGGREGATION_LOADED_EVENT = 'currency.aggregation.result.loaded';

    final public const CURRENCY_ID_SEARCH_RESULT_LOADED_EVENT = 'currency.id.search.result.loaded';

    final public const CURRENCY_TRANSLATION_WRITTEN_EVENT = 'currency_translation.written';

    final public const CURRENCY_TRANSLATION_DELETED_EVENT = 'currency_translation.deleted';

    final public const CURRENCY_TRANSLATION_LOADED_EVENT = 'currency_translation.loaded';

    final public const CURRENCY_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'currency_translation.search.result.loaded';

    final public const CURRENCY_TRANSLATION_AGGREGATION_LOADED_EVENT = 'currency_translation.aggregation.result.loaded';

    final public const CURRENCY_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'currency_translation.id.search.result.loaded';
}
