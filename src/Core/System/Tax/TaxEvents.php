<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class TaxEvents
{
    final public const TAX_WRITTEN_EVENT = 'tax.written';

    final public const TAX_DELETED_EVENT = 'tax.deleted';

    final public const TAX_LOADED_EVENT = 'tax.loaded';

    final public const TAX_SEARCH_RESULT_LOADED_EVENT = 'tax.search.result.loaded';

    final public const TAX_AGGREGATION_LOADED_EVENT = 'tax.aggregation.result.loaded';

    final public const TAX_ID_SEARCH_RESULT_LOADED_EVENT = 'tax.id.search.result.loaded';

    final public const TAX_AREA_RULE_WRITTEN_EVENT = 'tax_area_rule.written';

    final public const TAX_AREA_RULE_DELETED_EVENT = 'tax_area_rule.deleted';

    final public const TAX_AREA_RULE_LOADED_EVENT = 'tax_area_rule.loaded';

    final public const TAX_AREA_RULE_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule.search.result.loaded';

    final public const TAX_AREA_RULE_AGGREGATION_LOADED_EVENT = 'tax_area_rule.aggregation.result.loaded';

    final public const TAX_AREA_RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule.id.search.result.loaded';

    final public const TAX_AREA_RULE_TRANSLATION_WRITTEN_EVENT = 'tax_area_rule_translation.written';

    final public const TAX_AREA_RULE_TRANSLATION_DELETED_EVENT = 'tax_area_rule_translation.deleted';

    final public const TAX_AREA_RULE_TRANSLATION_LOADED_EVENT = 'tax_area_rule_translation.loaded';

    final public const TAX_AREA_RULE_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule_translation.search.result.loaded';

    final public const TAX_AREA_RULE_TRANSLATION_AGGREGATION_LOADED_EVENT = 'tax_area_rule_translation.aggregation.result.loaded';

    final public const TAX_AREA_RULE_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'tax_area_rule_translation.id.search.result.loaded';
}
