<?php declare(strict_types=1);

namespace Shopware\System\Currency\Aggregate\CurrencyTranslation\Event;

use Shopware\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencyTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var \Shopware\System\Currency\Aggregate\CurrencyTranslation\Collection\CurrencyTranslationBasicCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationBasicCollection $currencyTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCurrencyTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->currencyTranslations;
    }
}
