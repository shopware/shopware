<?php declare(strict_types=1);

namespace Shopware\Core\System\Touchpoint\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Core\System\Touchpoint\Collection\TouchpointBasicCollection;

class TouchpointBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'touchpoint.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
