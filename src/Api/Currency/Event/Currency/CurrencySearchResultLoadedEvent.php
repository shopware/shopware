<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\Currency;

use Shopware\Api\Currency\Struct\CurrencySearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencySearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.search.result.loaded';

    /**
     * @var CurrencySearchResult
     */
    protected $result;

    public function __construct(CurrencySearchResult $result)
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
