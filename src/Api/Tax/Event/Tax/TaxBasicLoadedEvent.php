<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Event\Tax;

use Shopware\Api\Tax\Collection\TaxBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class TaxBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'tax.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var TaxBasicCollection
     */
    protected $taxes;

    public function __construct(TaxBasicCollection $taxes, TranslationContext $context)
    {
        $this->context = $context;
        $this->taxes = $taxes;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getTaxes(): TaxBasicCollection
    {
        return $this->taxes;
    }
}
