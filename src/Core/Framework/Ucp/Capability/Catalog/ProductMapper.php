<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Catalog;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Maps a Shopware {@see SalesChannelProductEntity} into the UCP product
 * schema (`ucp/source/schemas/shopping/catalog_search.json#/$defs/product`).
 *
 * @internal
 */
#[Package('framework')]
class ProductMapper
{
    /**
     * @return array<string, mixed>
     */
    public function toUcpProduct(SalesChannelProductEntity $product, SalesChannelContext $context): array
    {
        $currency = $context->getCurrency()->getIsoCode();
        $price = $product->getCalculatedPrice();

        $variants = [];
        if ($product->getChildren() !== null && $product->getChildren()->count() > 0) {
            foreach ($product->getChildren() as $child) {
                if ($child instanceof SalesChannelProductEntity) {
                    $variants[] = $this->mapVariant($child, $currency);
                }
            }
        } else {
            $variants[] = $this->mapVariant($product, $currency);
        }

        $out = [
            'id' => $this->ucpProductId($product),
            'title' => $product->getTranslation('name') ?? $product->getName() ?? '',
            'variants' => $variants,
        ];

        $description = $product->getTranslation('description') ?? $product->getDescription();
        if (\is_string($description) && $description !== '') {
            $out['description'] = ['plain' => trim(strip_tags($description))];
        }

        if ($product->getManufacturer() !== null) {
            $out['brand'] = $product->getManufacturer()->getTranslation('name') ?? $product->getManufacturer()->getName();
        }

        $media = $product->getMedia();
        if ($media !== null && $media->count() > 0) {
            $urls = [];
            foreach ($media as $assoc) {
                $url = $assoc->getMedia()?->getUrl();
                if (\is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
            if ($urls !== []) {
                $out['media'] = array_map(static fn (string $u): array => ['url' => $u, 'type' => 'image'], $urls);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapVariant(SalesChannelProductEntity $product, string $currency): array
    {
        $price = $product->getCalculatedPrice();
        $stock = $product->getAvailableStock();

        return [
            'id' => $this->ucpProductId($product),
            'sku' => $product->getProductNumber(),
            'title' => $product->getTranslation('name') ?? $product->getName() ?? '',
            'description' => [
                'plain' => trim(strip_tags((string) ($product->getTranslation('description') ?? $product->getDescription() ?? ''))),
            ],
            'price' => [
                'amount' => (int) round($price->getUnitPrice() * 100),
                'currency' => $currency,
            ],
            'availability' => [
                'available' => $stock > 0,
                'status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
                'available_quantity' => $stock,
            ],
            'inputs' => [[
                'id' => $this->ucpProductId($product),
                'match' => 'exact',
            ]],
        ];
    }

    private function ucpProductId(SalesChannelProductEntity $product): string
    {
        $productNumber = $product->getProductNumber();

        return $productNumber !== '' ? $productNumber : $product->getId();
    }
}
