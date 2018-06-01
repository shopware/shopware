<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Tax\Struct\TaxSearchResult;

class TaxSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'tax.search.result.loaded';

    /**
     * @var TaxSearchResult
     */
    protected $result;

    public function __construct(TaxSearchResult $result)
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
