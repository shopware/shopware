<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Event\CurrencyTranslation;

use Shopware\Api\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CurrencyTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'currency_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CurrencyTranslationBasicCollection
     */
    protected $currencyTranslations;

    public function __construct(CurrencyTranslationBasicCollection $currencyTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->currencyTranslations = $currencyTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCurrencyTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->currencyTranslations;
    }
}
