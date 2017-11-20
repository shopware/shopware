<?php declare(strict_types=1);

namespace Shopware\Unit\Event\UnitTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Unit\Struct\UnitTranslationSearchResult;

class UnitTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'unit_translation.search.result.loaded';

    /**
     * @var UnitTranslationSearchResult
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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
