<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifier;

use Shopware\Api\Context\Struct\ContextCartModifierSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.search.result.loaded';

    /**
     * @var ContextCartModifierSearchResult
     */
    protected $result;

    public function __construct(ContextCartModifierSearchResult $result)
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
