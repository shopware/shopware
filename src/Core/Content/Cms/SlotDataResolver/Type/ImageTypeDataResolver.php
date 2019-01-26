<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms\SlotDataResolver\Type;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotTypeDataResolverInterface;
use Shopware\Core\Content\Cms\Storefront\Struct\ImageStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\InternalRequest;

class ImageTypeDataResolver implements SlotTypeDataResolverInterface
{
    public function getType(): string
    {
        return 'image';
    }

    public function collect(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context): ?CriteriaCollection
    {
        $config = $slot->getConfig();

        if (!isset($config['mediaId'])) {
            return null;
        }

        $criteria = new Criteria([$config['mediaId']]);

        $criteriaCollection = new CriteriaCollection();
        $criteriaCollection->add('media', MediaDefinition::class, $criteria);

        return $criteriaCollection;
    }

    public function enrich(CmsSlotEntity $slot, InternalRequest $request, CheckoutContext $context, SlotDataResolveResult $result): CmsSlotEntity
    {
        $slot = ImageStruct::createFrom($slot);

        $config = $slot->getConfig();

        if (isset($config['url'])) {
            $slot->setUrl($config['url']);
        }

        if (isset($config['mediaId'])) {
            $this->addMediaEntity($slot, $result, $config['mediaId']);
        }

        return $slot;
    }

    private function addMediaEntity(ImageStruct $slot, SlotDataResolveResult $result, string $mediaId): void
    {
        $slot->setMediaId($mediaId);

        $searchResult = $result->get('media');
        if (!$searchResult) {
            return;
        }

        /** @var MediaEntity|null $media */
        $media = $searchResult->get($mediaId);
        if (!$media) {
            return;
        }

        $slot->setMedia($media);
    }
}
