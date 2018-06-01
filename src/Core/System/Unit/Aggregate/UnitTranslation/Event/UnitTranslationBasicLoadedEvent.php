<?php declare(strict_types=1);

namespace Shopware\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Unit\Aggregate\UnitTranslation\Collection\UnitTranslationBasicCollection;

class UnitTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'unit_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var UnitTranslationBasicCollection
     */
    protected $unitTranslations;

    public function __construct(UnitTranslationBasicCollection $unitTranslations, Context $context)
    {
        $this->context = $context;
        $this->unitTranslations = $unitTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getUnitTranslations(): UnitTranslationBasicCollection
    {
        return $this->unitTranslations;
    }
}
