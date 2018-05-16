<?php declare(strict_types=1);

namespace Shopware\System\Currency\Event;

use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencyBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CurrencyBasicCollection
     */
    protected $currencies;

    public function __construct(CurrencyBasicCollection $currencies, ApplicationContext $context)
    {
        $this->context = $context;
        $this->currencies = $currencies;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return $this->currencies;
    }
}
