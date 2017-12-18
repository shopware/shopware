<?php declare(strict_types=1);

namespace Shopware\Api\Country\Event\CountryTranslation;

use Shopware\Api\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CountryTranslationBasicCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationBasicCollection $countryTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCountryTranslations(): CountryTranslationBasicCollection
    {
        return $this->countryTranslations;
    }
}
