<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;

class CurrencyBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct(CurrencyBasicCollection $currencies, Context $context)
    {
        $this->context = $context;
        $this->currencies = $currencies;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return $this->currencies;
    }
}
