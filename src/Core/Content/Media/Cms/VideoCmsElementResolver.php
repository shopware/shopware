<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\VideoStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class VideoCmsElementResolver extends AbstractCmsElementResolver
{
    /**
     * @internal
     */
    public function __construct(private readonly AbstractDefaultMediaResolver $mediaResolver)
    {
    }

    public function getType(): string
    {
        return 'video';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $mediaConfig = $slot->getFieldConfig()->get('media');

        if (
            $mediaConfig === null
            || $mediaConfig->isMapped()
            || $mediaConfig->isDefault()
            || $mediaConfig->getValue() === null
        ) {
            return null;
        }

        $criteria = new Criteria([$mediaConfig->getStringValue()]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $video = new VideoStruct();
        $slot->setData($video);

        $ariaLabelConfig = $config->get('ariaLabel');
        if ($ariaLabelConfig !== null) {
            $video->setAriaLabel($ariaLabelConfig->getStringValue());
        }

        $mediaConfig = $config->get('media');
        if ($mediaConfig && $mediaConfig->getValue()) {
            $this->addMediaEntity($slot, $video, $result, $mediaConfig, $resolverContext);
        }
    }

    private function addMediaEntity(
        CmsSlotEntity $slot,
        VideoStruct $video,
        ElementDataCollection $result,
        FieldConfig $config,
        ResolverContext $resolverContext
    ): void {
        if ($config->isDefault()) {
            $media = $this->mediaResolver->getDefaultCmsMediaEntity($config->getStringValue());

            if ($media) {
                $video->setMedia($media);
            }

            return;
        }

        if ($config->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $media = $this->resolveEntityValue($resolverContext->getEntity(), $config->getStringValue());

            if ($media instanceof MediaEntity) {
                $video->setMediaId($media->getUniqueIdentifier());
                $video->setMedia($media);
            }

            return;
        }

        if ($config->isStatic()) {
            $video->setMediaId($config->getStringValue());

            $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
            if (!$searchResult) {
                return;
            }

            $media = $searchResult->get($config->getStringValue());
            if (!$media instanceof MediaEntity) {
                return;
            }

            $video->setMedia($media);
        }
    }
}
