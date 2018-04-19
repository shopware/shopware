<?php

namespace Shopware\Shop\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Struct\ShopCollection;
use Symfony\Component\EventDispatcher\Event;

class ShopsLoadedEvent extends Event
{
    const NAME = 'shops.loaded';

    /**
     * @var ShopCollection
     */
    protected $shops;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShopCollection $shops, TranslationContext $context)
    {
        $this->shops = $shops;
        $this->context = $context;
    }

    public function getShops(): ShopCollection
    {
        return $this->shops;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }
}