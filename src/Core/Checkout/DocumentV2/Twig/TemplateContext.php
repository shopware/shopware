<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Twig;

use Shopware\Core\Checkout\DocumentV2\Config\CompanyInfo;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfig;
use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\InvoiceRenderData;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * Read-only flat-namespace view over an {@see AbstractRenderData} for Twig templates.
 *
 * Resolves dot-access (`config.companyName`, `config.displayHeader`, `config.documentDate`)
 * across the render data, its `DocumentConfig`, and its `CompanyInfo` plus a renderer-supplied
 * overrides map — preserving the historical flat `config.*` contract that document templates
 * and their plugin extensions rely on.
 *
 * Resolution order: overrides → render data → DocumentConfig → CompanyInfo → legacyConfig.
 * The legacyConfig fallback exists so templates and plugin extensions reading keys that
 * have not yet been promoted to typed properties keep working during the v6.7 → v6.8
 * deprecation window.
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

    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(
        AbstractRenderData $data,
        array $overrides = [],
    ) {
        $properties = $data->legacyConfig;

        foreach ([$data->company, $data->config, $data] as $source) {
            foreach (get_object_vars($source) as $key => $value) {
                $properties[$key] = $value;
            }
        }

        foreach ($overrides as $key => $value) {
            $properties[$key] = $value;
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
}
