<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Twig;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Read-only flat-namespace view over an {@see AbstractRenderData} for legacy Twig templates.
 *
 * Resolves dot-access (`config.companyName`, `config.displayHeader`, `config.documentDate`)
 * through an explicit compatibility map that preserves the historical flat `config.*` contract
 * document templates and their plugin extensions rely on.
 *
 * The legacyConfig fallback exists so keys that have not yet been promoted to typed properties
 * keep working during the v6.7 → v6.8 deprecation window.
 *
 * @internal
 *
 * @implements \ArrayAccess<string, mixed>
 *
 * @mixin DocumentConfig
 * @mixin CompanyInfo
 * @mixin InvoiceRenderData
 *
 * @property mixed $fileType
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

    public function __construct(
        AbstractRenderData $data,
        ?string $fileType = null,
        ?int $itemsPerPage = null,
    ) {
        $properties = array_replace(
            $data->legacyConfig,
            self::companyProperties($data->company),
            self::configProperties($data->config),
            self::renderDataProperties($data),
        );

        $properties['getAddressParts'] = $data->company->getAddressParts();

        if ($fileType !== null) {
            $properties['fileType'] = $fileType;
        }

        if ($itemsPerPage !== null) {
            $properties['itemsPerPage'] = $itemsPerPage;
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
    private static function companyProperties(CompanyInfo $company): array
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
            'displayHeader' => $config->displayHeader,
            'displayFooter' => $config->displayFooter,
            'displayPageCount' => $config->displayPageCount,
            'displayCompanyAddress' => $config->displayCompanyAddress,
            'displayReturnAddress' => $config->displayReturnAddress,
            'displayCustomerVatId' => $config->displayCustomerVatId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function renderDataProperties(AbstractRenderData $data): array
    {
        $properties = [
            'documentDate' => $data->documentDate,
            'documentNumber' => $data->documentNumber,
            'documentComment' => $data->documentComment,
        ];

        if (!$data instanceof InvoiceRenderData) {
            return $properties;
        }

        return [
            ...$properties,
            'intraCommunityDelivery' => $data->intraCommunityDelivery,
            'displayDivergentDeliveryAddress' => $data->displayDivergentDeliveryAddress,
            'displayLineItems' => $data->displayLineItems,
            'displayLineItemPosition' => $data->displayLineItemPosition,
            'displayPrices' => $data->displayPrices,
            'deliveryCountries' => $data->deliveryCountries,
            'custom' => $data->custom,
        ];
    }
}
