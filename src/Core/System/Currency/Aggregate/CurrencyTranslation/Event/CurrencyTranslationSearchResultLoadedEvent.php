<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationSearchResult;

class CurrencyTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
