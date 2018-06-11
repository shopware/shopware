<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationSearchResult;

class UnitTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'unit_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Unit\Aggregate\UnitTranslation\Struct\UnitTranslationSearchResult
     */
    protected $result;

    public function __construct(UnitTranslationSearchResult $result)
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
