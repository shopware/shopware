<?php

namespace Shopware\Category\Event;

use Shopware\Category\Struct\CategoryIdentityCollection;
use Shopware\Context\Struct\TranslationContext;
use Symfony\Component\EventDispatcher\Event;

class CategoryIdentityLoadedEvent extends Event
{
    const NAME = 'category.identity.loaded';

    /**
     * @var CategoryIdentityCollection
     */
    protected $identities;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CategoryIdentityCollection $identities, TranslationContext $context)
    {
        $this->identities = $identities;
        $this->context = $context;
    }

    public function getIdentities(): CategoryIdentityCollection
    {
        return $this->identities;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }
}