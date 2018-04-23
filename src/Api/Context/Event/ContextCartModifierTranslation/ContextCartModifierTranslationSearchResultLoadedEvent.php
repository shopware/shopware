<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifierTranslation;

use Shopware\Api\Context\Struct\ContextCartModifierTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier_translation.search.result.loaded';

    /**
     * @var ContextCartModifierTranslationSearchResult
     */
    protected $result;

    public function __construct(ContextCartModifierTranslationSearchResult $result)
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
