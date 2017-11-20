<?php declare(strict_types=1);

namespace Shopware\Shop\Collection;

use Shopware\Category\Collection\CategoryBasicCollection;
use Shopware\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Country\Collection\CountryAreaTranslationBasicCollection;
use Shopware\Country\Collection\CountryBasicCollection;
use Shopware\Country\Collection\CountryStateTranslationBasicCollection;
use Shopware\Country\Collection\CountryTranslationBasicCollection;
use Shopware\Currency\Collection\CurrencyBasicCollection;
use Shopware\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Listing\Collection\ListingFacetTranslationBasicCollection;
use Shopware\Listing\Collection\ListingSortingTranslationBasicCollection;
use Shopware\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Mail\Collection\MailTranslationBasicCollection;
use Shopware\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Media\Collection\MediaTranslationBasicCollection;
use Shopware\Order\Collection\OrderStateTranslationBasicCollection;
use Shopware\Payment\Collection\PaymentMethodBasicCollection;
use Shopware\Payment\Collection\PaymentMethodTranslationBasicCollection;
use Shopware\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Product\Collection\ProductTranslationBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodTranslationBasicCollection;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Tax\Collection\TaxAreaRuleTranslationBasicCollection;
use Shopware\Unit\Collection\UnitTranslationBasicCollection;

class ShopDetailCollection extends ShopBasicCollection
{
    /**
     * @var ShopDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getParent();
            })
        );
    }

    public function getTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getTemplate();
            })
        );
    }

    public function getDocumentTemplates(): ShopTemplateBasicCollection
    {
        return new ShopTemplateBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getDocumentTemplate();
            })
        );
    }

    public function getCategories(): CategoryBasicCollection
    {
        return new CategoryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCategory();
            })
        );
    }

    public function getCustomerGroups(): CustomerGroupBasicCollection
    {
        return new CustomerGroupBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCustomerGroup();
            })
        );
    }

    public function getFallbackTranslations(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getFallbackTranslation();
            })
        );
    }

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getPaymentMethod();
            })
        );
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return new ShippingMethodBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getShippingMethod();
            })
        );
    }

    public function getCountries(): CountryBasicCollection
    {
        return new CountryBasicCollection(
            $this->fmap(function (ShopDetailStruct $shop) {
                return $shop->getCountry();
            })
        );
    }

    public function getCategoryTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCategoryTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCategoryTranslations(): CategoryTranslationBasicCollection
    {
        $collection = new CategoryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCategoryTranslations()->getElements());
        }

        return $collection;
    }

    public function getCountryAreaTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountryAreaTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountryAreaTranslations(): CountryAreaTranslationBasicCollection
    {
        $collection = new CountryAreaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountryAreaTranslations()->getElements());
        }

        return $collection;
    }

    public function getCountryStateTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountryStateTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountryStateTranslations(): CountryStateTranslationBasicCollection
    {
        $collection = new CountryStateTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountryStateTranslations()->getElements());
        }

        return $collection;
    }

    public function getCountryTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCountryTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCountryTranslations(): CountryTranslationBasicCollection
    {
        $collection = new CountryTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCountryTranslations()->getElements());
        }

        return $collection;
    }

    public function getCurrencyTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCurrencyTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCurrencyTranslations(): CurrencyTranslationBasicCollection
    {
        $collection = new CurrencyTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCurrencyTranslations()->getElements());
        }

        return $collection;
    }

    public function getCustomerGroupTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomerGroupTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCustomerGroupTranslations(): CustomerGroupTranslationBasicCollection
    {
        $collection = new CustomerGroupTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomerGroupTranslations()->getElements());
        }

        return $collection;
    }

    public function getListingFacetTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getListingFacetTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getListingFacetTranslations(): ListingFacetTranslationBasicCollection
    {
        $collection = new ListingFacetTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getListingFacetTranslations()->getElements());
        }

        return $collection;
    }

    public function getListingSortingTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getListingSortingTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getListingSortingTranslations(): ListingSortingTranslationBasicCollection
    {
        $collection = new ListingSortingTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getListingSortingTranslations()->getElements());
        }

        return $collection;
    }

    public function getLocaleTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getLocaleTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getLocaleTranslations(): LocaleTranslationBasicCollection
    {
        $collection = new LocaleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getLocaleTranslations()->getElements());
        }

        return $collection;
    }

    public function getMailTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMailTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMailTranslations(): MailTranslationBasicCollection
    {
        $collection = new MailTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMailTranslations()->getElements());
        }

        return $collection;
    }

    public function getMediaAlbumTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMediaAlbumTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMediaAlbumTranslations(): MediaAlbumTranslationBasicCollection
    {
        $collection = new MediaAlbumTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMediaAlbumTranslations()->getElements());
        }

        return $collection;
    }

    public function getMediaTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMediaTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMediaTranslations(): MediaTranslationBasicCollection
    {
        $collection = new MediaTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMediaTranslations()->getElements());
        }

        return $collection;
    }

    public function getOrderStateTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderStateTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderStateTranslations(): OrderStateTranslationBasicCollection
    {
        $collection = new OrderStateTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderStateTranslations()->getElements());
        }

        return $collection;
    }

    public function getPaymentMethodTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPaymentMethodTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getPaymentMethodTranslations(): PaymentMethodTranslationBasicCollection
    {
        $collection = new PaymentMethodTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPaymentMethodTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductManufacturerTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductManufacturerTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationBasicCollection
    {
        $collection = new ProductManufacturerTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductManufacturerTranslations()->getElements());
        }

        return $collection;
    }

    public function getProductTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProductTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProductTranslations(): ProductTranslationBasicCollection
    {
        $collection = new ProductTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProductTranslations()->getElements());
        }

        return $collection;
    }

    public function getShippingMethodTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShippingMethodTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getShippingMethodTranslations(): ShippingMethodTranslationBasicCollection
    {
        $collection = new ShippingMethodTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShippingMethodTranslations()->getElements());
        }

        return $collection;
    }

    public function getTaxAreaRuleTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTaxAreaRuleTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTaxAreaRuleTranslations(): TaxAreaRuleTranslationBasicCollection
    {
        $collection = new TaxAreaRuleTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTaxAreaRuleTranslations()->getElements());
        }

        return $collection;
    }

    public function getUnitTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getUnitTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getUnitTranslations(): UnitTranslationBasicCollection
    {
        $collection = new UnitTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getUnitTranslations()->getElements());
        }

        return $collection;
    }

    public function getAllCurrencyUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCurrencyUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAllCurrencies(): CurrencyBasicCollection
    {
        $collection = new CurrencyBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCurrencies()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShopDetailStruct::class;
    }
}
