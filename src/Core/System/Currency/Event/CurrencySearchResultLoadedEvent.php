<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Currency\Struct\CurrencySearchResult;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
