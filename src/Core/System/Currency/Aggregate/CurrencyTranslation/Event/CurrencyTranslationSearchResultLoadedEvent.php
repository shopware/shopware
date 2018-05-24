<?php declare(strict_types=1);

namespace Shopware\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationSearchResult;

class CurrencyTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
