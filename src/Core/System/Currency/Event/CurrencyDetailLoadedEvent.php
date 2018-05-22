<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\Event\CurrencyTranslationBasicLoadedEvent;
use Shopware\System\Currency\Collection\CurrencyDetailCollection;

class CurrencyDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CurrencyDetailCollection
     */
    protected $currencies;

    public function __construct(CurrencyDetailCollection $currencies, ApplicationContext $context)
    {
        $this->context = $context;
        $this->currencies = $currencies;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCurrencies(): CurrencyDetailCollection
    {
        return $this->currencies;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->currencies->getTranslations()->count() > 0) {
            $events[] = new CurrencyTranslationBasicLoadedEvent($this->currencies->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
