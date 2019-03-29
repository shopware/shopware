<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\ResolverContext;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ListingResolverContext extends ResolverContext
{
    /**
     * @var EntitySearchResult
     */
    protected $searchResult;

    /**
     * @var EntityDefinition|string
     */
    protected $definition;

    public function __construct(CheckoutContext $context, string $definition, EntitySearchResult $searchResult)
    {
        parent::__construct($context);

        $this->searchResult = $searchResult;
        $this->definition = $definition;
    }

    public function getSearchResult(): EntitySearchResult
    {
        return $this->searchResult;
    }

    public function getDefinition()
    {
        return $this->definition;
    }
}
