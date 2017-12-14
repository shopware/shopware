<?php declare(strict_types=1);

namespace Shopware\Api\Unit\Event\UnitTranslation;

use Shopware\Api\Unit\Collection\UnitTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class UnitTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'unit_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var UnitTranslationBasicCollection
     */
    protected $unitTranslations;

    public function __construct(UnitTranslationBasicCollection $unitTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->unitTranslations = $unitTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getUnitTranslations(): UnitTranslationBasicCollection
    {
        return $this->unitTranslations;
    }
}
