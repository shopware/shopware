<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentCompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentDisplayOptions;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\DocumentMetaRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Read-only flat `config.*` view over the render data for the legacy Twig templates.
 *
 * Builds a compatibility map from the shared {@see DocumentMetaRenderData} and flattens the public
 * fields of each type-specific DTO on top, so no per-type knowledge lives here. The legacyConfig
 * fallback keeps not-yet-typed keys working during the v6.7 → v6.8 window.
 *
 * @internal
 *
 * @implements \ArrayAccess<string, mixed>
 *
 * @mixin DocumentConfig
 * @mixin DocumentCompanyInfo
 * @mixin DocumentDisplayOptions
 * @mixin DocumentMetaRenderData
 *
 * @property mixed $getAddressParts
 * @property mixed $displayAdditionalNoteDelivery
 */
#[Package('after-sales')]
final readonly class TemplateContext implements \ArrayAccess
{
    /**
     * @var array<string, mixed>
     */
    private array $properties;

    /**
     * @param array<AbstractRenderData> $typeData
     */
    public function __construct(DocumentMetaRenderData $meta, array $typeData = [])
    {
        $shared = array_replace(
            self::companyProperties($meta->company),
            self::configProperties($meta->config),
            self::metaProperties($meta),
            ['getAddressParts' => $meta->company->getAddressParts()],
        );

        $properties = array_replace($meta->legacyConfig, $shared);

        $typeKeys = [];

        foreach ($typeData as $data) {
            foreach (get_object_vars($data) as $key => $value) {
                if (\array_key_exists($key, $shared) || isset($typeKeys[$key])) {
                    throw DocumentV2Exception::templateContextPropertyCollision($key);
                }

                $typeKeys[$key] = true;
                $properties[$key] = $value;
            }
        }

        $this->properties = $properties;
    }

    public function __get(string $name): mixed
    {
        return $this->properties[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return \array_key_exists($name, $this->properties);
    }

    public function offsetExists(mixed $offset): bool
    {
        return \array_key_exists((string) $offset, $this->properties);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->properties[(string) $offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw DocumentV2Exception::templateContextReadOnly((string) $offset);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw DocumentV2Exception::templateContextReadOnly((string) $offset);
    }

    /**
     * @return array<string, mixed>
     */
    private static function companyProperties(DocumentCompanyInfo $company): array
    {
        return [
            'companyName' => $company->companyName,
            'companyStreet' => $company->companyStreet,
            'companyZipcode' => $company->companyZipcode,
            'companyCity' => $company->companyCity,
            'companyCountry' => $company->companyCountry,
            'companyEmail' => $company->companyEmail,
            'companyPhone' => $company->companyPhone,
            'companyUrl' => $company->companyUrl,
            'executiveDirector' => $company->executiveDirector,
            'taxNumber' => $company->taxNumber,
            'taxOffice' => $company->taxOffice,
            'vatId' => $company->vatId,
            'bankName' => $company->bankName,
            'bankIban' => $company->bankIban,
            'bankBic' => $company->bankBic,
            'placeOfJurisdiction' => $company->placeOfJurisdiction,
            'placeOfFulfillment' => $company->placeOfFulfillment,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function configProperties(DocumentConfig $config): array
    {
        return [
            'pageSize' => $config->pageSize,
            'pageOrientation' => $config->pageOrientation,
            'itemsPerPage' => $config->itemsPerPage,
            'filenamePrefix' => $config->filenamePrefix,
            'filenameSuffix' => $config->filenameSuffix,
            'logo' => $config->logo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function metaProperties(DocumentMetaRenderData $meta): array
    {
        return [
            'documentDate' => $meta->documentDate,
            'documentNumber' => $meta->documentNumber,
            'documentComment' => $meta->documentComment,
            'displayHeader' => $meta->display->displayHeader,
            'displayFooter' => $meta->display->displayFooter,
            'displayPageCount' => $meta->display->displayPageCount,
            'displayCompanyAddress' => $meta->display->displayCompanyAddress,
            'displayReturnAddress' => $meta->display->displayReturnAddress,
            'displayCustomerVatId' => $meta->display->displayCustomerVatId,
            'displayLineItems' => $meta->display->displayLineItems,
            'displayLineItemPosition' => $meta->display->displayLineItemPosition,
            'displayPrices' => $meta->display->displayPrices,
            'displayDivergentDeliveryAddress' => $meta->display->displayDivergentDeliveryAddress,
            'deliveryCountries' => $meta->display->deliveryCountries,
        ];
    }
}
