<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Struct\ContextCartModifierSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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
