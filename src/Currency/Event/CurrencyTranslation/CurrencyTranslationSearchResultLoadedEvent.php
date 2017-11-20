<?php declare(strict_types=1);

namespace Shopware\Currency\Event\CurrencyTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyTranslationSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CurrencyTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'currency_translation.search.result.loaded';

    /**
     * @var CurrencyTranslationSearchResult
     */
    protected $result;

    public function __construct(CurrencyTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
