<?php declare(strict_types=1);

namespace Shopware\Holiday\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Holiday\Struct\HolidayBasicCollection;

class HolidayBasicLoadedEvent extends NestedEvent
{
    const NAME = 'holiday.basic.loaded';

    /**
     * @var HolidayBasicCollection
     */
    protected $holidaies;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(HolidayBasicCollection $holidaies, TranslationContext $context)
    {
        $this->holidaies = $holidaies;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getHolidaies(): HolidayBasicCollection
    {
        return $this->holidaies;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
