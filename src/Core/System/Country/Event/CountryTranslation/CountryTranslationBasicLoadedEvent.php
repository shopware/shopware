<?php declare(strict_types=1);

namespace Shopware\System\Country\Event\CountryTranslation;

use Shopware\System\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CountryTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'country_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CountryTranslationBasicCollection
     */
    protected $countryTranslations;

    public function __construct(CountryTranslationBasicCollection $countryTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->countryTranslations = $countryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCountryTranslations(): CountryTranslationBasicCollection
    {
        return $this->countryTranslations;
    }
}
