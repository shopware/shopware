<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ManufacturerLogoStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ManufacturerLogoCmsElementResolver extends AbstractProductDetailCmsElementResolver
{
    public function getType(): string
    {
        return 'manufacturer-logo';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaConfig = $slot->getFieldConfig()->get('media');
        if ($mediaConfig === null || $mediaConfig->isMapped() || $mediaConfig->getValue() === null) {
            return parent::collect($slot, $resolverContext);
        }

        $criteria = new Criteria([$mediaConfig->getStringValue()]);

        $criteriaCollection = parent::collect($slot, $resolverContext) ?? new CriteriaCollection();
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $manufacturerStruct = new ManufacturerLogoStruct();
        $slot->setData($manufacturerStruct);

        $urlConfig = $config->get('url');
        if ($urlConfig !== null) {
            $manufacturerStruct->setUrl($this->getConfigUrl($urlConfig, $resolverContext));
        }

        $newTabConfig = $config->get('newTab');
        if ($newTabConfig !== null) {
            $manufacturerStruct->setNewTab($newTabConfig->getBoolValue());
        }

        $mediaConfig = $config->get('media');

        if ($mediaConfig !== null) {
            $media = $this->getMedia($slot, $result, $mediaConfig, $resolverContext);
            if ($media !== null) {
                $manufacturerStruct->setMedia($media);
                $manufacturerStruct->setMediaId($media->getId());
            }
        }

        if ($resolverContext instanceof EntityResolverContext && $resolverContext->getDefinition() instanceof SalesChannelProductDefinition) {
            /** @var SalesChannelProductEntity $product */
            $product = $resolverContext->getEntity();
            $manufacturerStruct->setManufacturer($product->getManufacturer());
        }
    }

    private function getConfigUrl(FieldConfig $config, ResolverContext $resolverContext): ?string
    {
        if ($config->isStatic()) {
            return $config->getStringValue();
        }

        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        return $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());
    }

    private function getMedia(CmsSlotEntity $slot, ElementDataCollection $result, FieldConfig $config, ResolverContext $resolverContext): ?MediaEntity
    {
        if ($config->isStatic()) {
            $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
            if (!$searchResult) {
                return null;
            }

            /** @var MediaEntity|null $media */
            $media = $searchResult->get($config->getValue());

            return $media;
        }

        if (!$resolverContext instanceof EntityResolverContext) {
            return null;
        }

        /** @var MediaEntity|null $media */
        $media = $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());

        return $media;
    }
}
