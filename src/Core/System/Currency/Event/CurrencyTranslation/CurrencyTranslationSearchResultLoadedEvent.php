<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event\CurrencyTranslation;

use Shopware\System\Currency\Struct\CurrencyTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencyTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
