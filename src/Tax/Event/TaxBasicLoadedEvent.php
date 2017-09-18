<?php declare(strict_types=1);

namespace Shopware\Tax\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Tax\Struct\TaxBasicCollection;

class TaxBasicLoadedEvent extends NestedEvent
{
    const NAME = 'tax.basic.loaded';

    /**
     * @var TaxBasicCollection
     */
    protected $taxs;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(TaxBasicCollection $taxs, TranslationContext $context)
    {
        $this->taxs = $taxs;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getTaxs(): TaxBasicCollection
    {
        return $this->taxs;
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
