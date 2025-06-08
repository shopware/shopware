<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('inventory')]
class ProductHydrator extends EntityHydrator
{
    /**
     * @param array<string, string> $row
     *
     * @throws \Exception
     */
    protected function assign(EntityDefinition $definition, Entity $entity, string $root, array $row, Context $context): Entity
    {
        if (isset($row[$root . '.id'])) {
            $entity->id = Uuid::fromBytesToHex($row[$root . '.id']);
        }
        if (isset($row[$root . '.versionId'])) {
            $entity->versionId = Uuid::fromBytesToHex($row[$root . '.versionId']);
        }
        if (isset($row[$root . '.parentId'])) {
            $entity->parentId = Uuid::fromBytesToHex($row[$root . '.parentId']);
        }
        if (isset($row[$root . '.manufacturerId'])) {
            $entity->manufacturerId = Uuid::fromBytesToHex($row[$root . '.manufacturerId']);
        }
        if (isset($row[$root . '.unitId'])) {
            $entity->unitId = Uuid::fromBytesToHex($row[$root . '.unitId']);
        }
        if (isset($row[$root . '.taxId'])) {
            $entity->taxId = Uuid::fromBytesToHex($row[$root . '.taxId']);
        }
        if (isset($row[$root . '.coverId'])) {
            $entity->coverId = Uuid::fromBytesToHex($row[$root . '.coverId']);
        }
        if (isset($row[$root . '.deliveryTimeId'])) {
            $entity->deliveryTimeId = Uuid::fromBytesToHex($row[$root . '.deliveryTimeId']);
        }
        if (isset($row[$root . '.featureSetId'])) {
            $entity->featureSetId = Uuid::fromBytesToHex($row[$root . '.featureSetId']);
        }
        if (isset($row[$root . '.canonicalProductId'])) {
            $entity->canonicalProductId = Uuid::fromBytesToHex($row[$root . '.canonicalProductId']);
        }
        if (isset($row[$root . '.cmsPageId'])) {
            $entity->cmsPageId = Uuid::fromBytesToHex($row[$root . '.cmsPageId']);
        }
        if (\array_key_exists($root . '.price', $row)) {
            $entity->price = $definition->decode('price', self::value($row, $root, 'price'));
        }
        if (isset($row[$root . '.productNumber'])) {
            $entity->productNumber = $row[$root . '.productNumber'];
        }
        if (isset($row[$root . '.stock'])) {
            $entity->stock = (int) $row[$root . '.stock'];
        }
        if (isset($row[$root . '.restockTime'])) {
            $entity->restockTime = (int) $row[$root . '.restockTime'];
        }
        if (isset($row[$root . '.autoIncrement'])) {
            $entity->autoIncrement = (int) $row[$root . '.autoIncrement'];
        }
        if (isset($row[$root . '.active'])) {
            $entity->active = (bool) $row[$root . '.active'];
        }
        if (isset($row[$root . '.availableStock'])) {
            $entity->availableStock = (int) $row[$root . '.availableStock'];
        }
        if (isset($row[$root . '.available'])) {
            $entity->available = (bool) $row[$root . '.available'];
        }
        if (isset($row[$root . '.isCloseout'])) {
            $entity->isCloseout = (bool) $row[$root . '.isCloseout'];
        }
        if (isset($row[$root . '.displayGroup'])) {
            $entity->displayGroup = $row[$root . '.displayGroup'];
        }
        if (isset($row[$root . '.states'])) {
            $entity->states = $definition->decode('states', self::value($row, $root, 'states'));
        }
        if (isset($row[$root . '.variantListingConfig'])) {
            $entity->variantListingConfig = $definition->decode('variantListingConfig', self::value($row, $root, 'variantListingConfig'));
        }
        if (\array_key_exists($root . '.variantRestrictions', $row)) {
            $entity->variantRestrictions = $definition->decode('variantRestrictions', self::value($row, $root, 'variantRestrictions'));
        }
        if (isset($row[$root . '.manufacturerNumber'])) {
            $entity->manufacturerNumber = $row[$root . '.manufacturerNumber'];
        }
        if (isset($row[$root . '.ean'])) {
            $entity->ean = $row[$root . '.ean'];
        }
        if (isset($row[$root . '.purchaseSteps'])) {
            $entity->purchaseSteps = (int) $row[$root . '.purchaseSteps'];
        }
        if (isset($row[$root . '.maxPurchase'])) {
            $entity->maxPurchase = (int) $row[$root . '.maxPurchase'];
        }
        if (isset($row[$root . '.minPurchase'])) {
            $entity->minPurchase = (int) $row[$root . '.minPurchase'];
        }
        if (isset($row[$root . '.purchaseUnit'])) {
            $entity->purchaseUnit = (float) $row[$root . '.purchaseUnit'];
        }
        if (isset($row[$root . '.referenceUnit'])) {
            $entity->referenceUnit = (float) $row[$root . '.referenceUnit'];
        }
        if (isset($row[$root . '.shippingFree'])) {
            $entity->shippingFree = (bool) $row[$root . '.shippingFree'];
        }
        if (\array_key_exists($root . '.purchasePrices', $row)) {
            $entity->purchasePrices = $definition->decode('purchasePrices', self::value($row, $root, 'purchasePrices'));
        }
        if (isset($row[$root . '.markAsTopseller'])) {
            $entity->markAsTopseller = (bool) $row[$root . '.markAsTopseller'];
        }
        if (isset($row[$root . '.weight'])) {
            $entity->weight = (float) $row[$root . '.weight'];
        }
        if (isset($row[$root . '.width'])) {
            $entity->width = (float) $row[$root . '.width'];
        }
        if (isset($row[$root . '.height'])) {
            $entity->height = (float) $row[$root . '.height'];
        }
        if (isset($row[$root . '.length'])) {
            $entity->length = (float) $row[$root . '.length'];
        }
        if (isset($row[$root . '.releaseDate'])) {
            $entity->releaseDate = new \DateTimeImmutable($row[$root . '.releaseDate']);
        }
        if (isset($row[$root . '.ratingAverage'])) {
            $entity->ratingAverage = (float) $row[$root . '.ratingAverage'];
        }
        if (\array_key_exists($root . '.categoryTree', $row)) {
            $entity->categoryTree = $definition->decode('categoryTree', self::value($row, $root, 'categoryTree'));
        }
        if (\array_key_exists($root . '.propertyIds', $row)) {
            $entity->propertyIds = $definition->decode('propertyIds', self::value($row, $root, 'propertyIds'));
        }
        if (\array_key_exists($root . '.optionIds', $row)) {
            $entity->optionIds = $definition->decode('optionIds', self::value($row, $root, 'optionIds'));
        }
        if (\array_key_exists($root . '.streamIds', $row)) {
            $entity->streamIds = $definition->decode('streamIds', self::value($row, $root, 'streamIds'));
        }
        if (\array_key_exists($root . '.tagIds', $row)) {
            $entity->tagIds = $definition->decode('tagIds', self::value($row, $root, 'tagIds'));
        }
        if (\array_key_exists($root . '.categoryIds', $row)) {
            $entity->categoryIds = $definition->decode('categoryIds', self::value($row, $root, 'categoryIds'));
        }
        if (isset($row[$root . '.childCount'])) {
            $entity->childCount = (int) $row[$root . '.childCount'];
        }
        if (isset($row[$root . '.customFieldSetSelectionActive'])) {
            $entity->customFieldSetSelectionActive = (bool) $row[$root . '.customFieldSetSelectionActive'];
        }
        if (isset($row[$root . '.sales'])) {
            $entity->sales = (int) $row[$root . '.sales'];
        }
        if (\array_key_exists($root . '.cheapestPrice', $row)) {
            $entity->cheapestPrice = $definition->decode('cheapestPrice', self::value($row, $root, 'cheapestPrice'));
        }
        if (isset($row[$root . '.createdAt'])) {
            $entity->createdAt = new \DateTimeImmutable($row[$root . '.createdAt']);
        }
        if (isset($row[$root . '.updatedAt'])) {
            $entity->updatedAt = new \DateTimeImmutable($row[$root . '.updatedAt']);
        }
        $entity->deliveryTime = $this->manyToOne($row, $root, $definition->getField('deliveryTime'), $context);
        $entity->tax = $this->manyToOne($row, $root, $definition->getField('tax'), $context);
        $entity->manufacturer = $this->manyToOne($row, $root, $definition->getField('manufacturer'), $context);
        $entity->unit = $this->manyToOne($row, $root, $definition->getField('unit'), $context);
        $entity->cover = $this->manyToOne($row, $root, $definition->getField('cover'), $context);
        $entity->featureSet = $this->manyToOne($row, $root, $definition->getField('featureSet'), $context);
        $entity->cmsPage = $this->manyToOne($row, $root, $definition->getField('cmsPage'), $context);
        $entity->canonicalProduct = $this->manyToOne($row, $root, $definition->getField('canonicalProduct'), $context);

        $this->translate($definition, $entity, $row, $root, $context, $definition->getTranslatedFields());
        $this->hydrateFields($definition, $entity, $root, $row, $context, $definition->getExtensionFields());
        $this->customFields($definition, $row, $root, $entity, $definition->getField('customFields'), $context);
        $this->manyToMany($row, $root, $entity, $definition->getField('options'));
        $this->manyToMany($row, $root, $entity, $definition->getField('properties'));
        $this->manyToMany($row, $root, $entity, $definition->getField('categories'));
        $this->manyToMany($row, $root, $entity, $definition->getField('streams'));
        $this->manyToMany($row, $root, $entity, $definition->getField('categoriesRo'));
        $this->manyToMany($row, $root, $entity, $definition->getField('tags'));
        $this->manyToMany($row, $root, $entity, $definition->getField('customFieldSets'));

        return $entity;
    }
}
