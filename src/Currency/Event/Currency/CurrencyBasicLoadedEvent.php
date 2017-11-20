<?php declare(strict_types=1);

namespace Shopware\Currency\Event\Currency;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Collection\CurrencyBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class CurrencyBasicLoadedEvent extends NestedEvent
{
    const NAME = 'currency.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct(CurrencyBasicCollection $currencies, TranslationContext $context)
    {
        $this->context = $context;
        $this->currencies = $currencies;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return $this->currencies;
    }
}
