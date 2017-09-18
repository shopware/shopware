<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\Shop\Struct\ShopBasicCollection;

class ShopBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shop.basic.loaded';

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShopBasicCollection $shops, TranslationContext $context)
    {
        $this->shops = $shops;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new CurrencyBasicLoadedEvent($this->shops->getCurrencies(), $this->context),
            new LocaleBasicLoadedEvent($this->shops->getLocales(), $this->context),
        ]);
    }
}
