<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifier;

use Shopware\Core\Framework\Context;
use Shopware\Core\Application\Context\Struct\ContextCartModifierSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
