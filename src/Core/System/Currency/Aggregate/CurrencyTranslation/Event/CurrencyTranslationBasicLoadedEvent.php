<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;

class CurrencyTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationBasicCollection $currencyTranslations, Context $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCurrencyTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->currencyTranslations;
    }
}
