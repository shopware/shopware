<?php declare(strict_types=1);

namespace Shopware\System\Touchpoint\Event;

use Shopware\System\Touchpoint\Collection\TouchpointBasicCollection;
use Shopware\Framework\Context;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Currency\Event\CurrencyBasicLoadedEvent;

class TouchpointBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'touchpoint.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var TouchpointBasicCollection
     */
    protected $touchpoints;

    public function __construct(TouchpointBasicCollection $touchpoints, Context $context)
    {
        $this->context = $context;
        $this->touchpoints = $touchpoints;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getTouchpoints(): TouchpointBasicCollection
    {
        return $this->touchpoints;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->touchpoints->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->touchpoints->getLanguages(), $this->context);
        }
        if ($this->touchpoints->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->touchpoints->getCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
