<?php declare(strict_types=1);

namespace Shopware\Unit\Event\Unit;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Unit\Struct\UnitSearchResult;

class UnitSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'unit.search.result.loaded';

    /**
     * @var UnitSearchResult
     */
    protected $result;

    public function __construct(UnitSearchResult $result)
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
