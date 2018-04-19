<?php

namespace Shopware\Category\Event;

use Shopware\Category\Struct\CategoryCollection;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\Event;

class CategoryLoadedEvent extends Event
{
    const NAME = 'category.loaded';

    /**
     * @var CategoryCollection
     */
    protected $categories;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CategoryCollection $identities, TranslationContext $context)
    {
        $this->categories = $identities;
        $this->context = $context;
    }

    public function getCategories(): CategoryCollection
    {
        return $this->categories;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }
}