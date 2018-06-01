<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event;

use Shopware\Application\Application\Collection\ApplicationBasicCollection;
use Shopware\Framework\Context;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Currency\Event\CurrencyBasicLoadedEvent;

class ApplicationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'application.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ApplicationBasicCollection
     */
    protected $applications;

    public function __construct(ApplicationBasicCollection $applications, Context $context)
    {
        $this->context = $context;
        $this->applications = $applications;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getApplications(): ApplicationBasicCollection
    {
        return $this->applications;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->applications->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->applications->getLanguages(), $this->context);
        }
        if ($this->applications->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->applications->getCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
