<?php declare(strict_types=1);

namespace Shopware\Application\Application\Collection;

use Shopware\Application\Application\Struct\ApplicationBasicStruct;
use Shopware\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Framework\ORM\EntityCollection;
use Shopware\Application\Language\Collection\LanguageBasicCollection;

class ApplicationBasicCollection extends EntityCollection
{
    /**
     * @var ApplicationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? ApplicationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): ApplicationBasicStruct
    {
        return parent::current();
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getLanguageId() === $id;
        });
    }

    public function getCurrencyIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getCurrencyId();
        });
    }

    public function filterByCurrencyId(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getCurrencyId() === $id;
        });
    }

    public function getPaymentMethodIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getPaymentMethodId();
        });
    }

    public function filterByPaymentMethodId(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getPaymentMethodId() === $id;
        });
    }

    public function getShippingMethodIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getShippingMethodId();
        });
    }

    public function filterByShippingMethodId(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getShippingMethodId() === $id;
        });
    }

    public function getCountryIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getCountryId();
        });
    }

    public function filterByCountryId(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getCountryId() === $id;
        });
    }

    public function getCatalogIds(): array
    {
        return $this->fmap(function (ApplicationBasicStruct $application) {
            return $application->getCatalogIds();
        });
    }

    public function filterByCatalogIds(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getCatalogIds() === $id;
        });
    }

    public function filterByCurrencyIds(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getCurrencyIds() === $id;
        });
    }

    public function filterByLanguageIds(string $id): ApplicationBasicCollection
    {
        return $this->filter(function (ApplicationBasicStruct $application) use ($id) {
            return $application->getLanguageIds() === $id;
        });
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (ApplicationBasicStruct $application) {
                return $application->getLanguage();
            })
        );
    }

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (ApplicationBasicStruct $application) {
                return $application->getCurrency();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return ApplicationBasicStruct::class;
    }
}
