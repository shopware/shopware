<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Cms\Type;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\Exception\DuplicateCriteriaKeyException;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderItemStruct;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class ImageSliderTypeDataResolver extends AbstractCmsElementResolver
{
    public function getType(): string
    {
        return 'image-slider';
    }

    /**
     * @throws DuplicateCriteriaKeyException
     * @throws InconsistentCriteriaIdsException
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        $config = $slot->getFieldConfig();
        $sliderItemsConfig = $config->get('sliderItems');

        if (!$sliderItemsConfig || $sliderItemsConfig->isMapped()) {
            return null;
        }

        $sliderItems = $sliderItemsConfig->getValue();

        $mediaIds = array_column($sliderItems, 'mediaId');

        $criteria = new Criteria($mediaIds);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('media_' . $slot->getUniqueIdentifier(), MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $imageSlider = new ImageSliderStruct();
        $slot->setData($imageSlider);

        if (($navigation = $config->get('navigation')) && $navigation->isStatic()) {
            $imageSlider->setNavigation($navigation->getValue());
        }

        $sliderItemsConfig = $config->get('sliderItems');

        if (!$sliderItemsConfig) {
            return;
        }

        if ($sliderItemsConfig->isStatic()) {
            foreach ($sliderItemsConfig->getValue() as $sliderItem) {
                $this->addMedia($slot, $imageSlider, $result, $sliderItem);
            }
        }

        if ($sliderItemsConfig->isMapped() && $resolverContext instanceof EntityResolverContext) {
            $sliderItems = $this->resolveEntityValue($resolverContext->getEntity(), $sliderItemsConfig->getValue());

            if (!$sliderItems) {
                return;
            }

            foreach ($sliderItems->getMedia() as $media) {
                $imageSliderItem = new ImageSliderItemStruct();
                $imageSliderItem->setMedia($media);
                $imageSlider->addSliderItem($imageSliderItem);
            }
        }
    }

    private function addMedia(CmsSlotEntity $slot, ImageSliderStruct $imageSlider, ElementDataCollection $result, array $config): void
    {
        $imageSliderItem = new ImageSliderItemStruct();

        if (!empty($config['url'])) {
            $imageSliderItem->setUrl($config['url']);
            $imageSliderItem->setNewTab($config['newTab']);
        }

        $searchResult = $result->get('media_' . $slot->getUniqueIdentifier());
        if (!$searchResult) {
            return;
        }

        /** @var MediaEntity|null $media */
        $media = $searchResult->get($config['mediaId']);
        if (!$media) {
            return;
        }

        $imageSliderItem->setMedia($media);
        $imageSlider->addSliderItem($imageSliderItem);
    }
}
