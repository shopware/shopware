<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\Shop;

use Shopware\System\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Api\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shop.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    public function __construct(ShopBasicCollection $shops, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shops = $shops;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shops->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->shops->getLocales(), $this->context);
        }
        if ($this->shops->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->shops->getCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
