<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\Currency;

use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencyBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.basic.loaded';

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
