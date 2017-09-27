<?php

namespace Shopware\ProductMedia\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\ProductMedia\Extension\ProductMediaExtension;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class ProductMediaBasicFactory extends Factory
{
    const ROOT_NAME = 'product_media';
    const EXTENSION_NAMESPACE = 'productMedia';

    const FIELDS = [
       'uuid' => 'uuid',
       'product_uuid' => 'product_uuid',
       'is_cover' => 'is_cover',
       'position' => 'position',
       'product_detail_uuid' => 'product_detail_uuid',
       'media_uuid' => 'media_uuid',
       'parent_uuid' => 'parent_uuid',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'description' => 'translation.description',
    ];

    /**
     * @var MediaBasicFactory
     */
    protected $mediaFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        MediaBasicFactory $mediaFactory
    ) {
        parent::__construct($connection, $registry);
        $this->mediaFactory = $mediaFactory;
    }

    public function hydrate(
        array $data,
        ProductMediaBasicStruct $productMedia,
        QuerySelection $selection,
        TranslationContext $context
    ): ProductMediaBasicStruct {
        $productMedia->setUuid((string) $data[$selection->getField('uuid')]);
        $productMedia->setProductUuid((string) $data[$selection->getField('product_uuid')]);
        $productMedia->setIsCover((int) $data[$selection->getField('is_cover')]);
        $productMedia->setPosition((int) $data[$selection->getField('position')]);
        $productMedia->setProductDetailUuid(isset($data[$selection->getField('product_detail_uuid')]) ? (string) $data[$selection->getField('product_detail_uuid')] : null);
        $productMedia->setMediaUuid((string) $data[$selection->getField('media_uuid')]);
        $productMedia->setParentUuid(isset($data[$selection->getField('parent_uuid')]) ? (string) $data[$selection->getField('parent_uuid')] : null);
        $productMedia->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $productMedia->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $productMedia->setDescription((string) $data[$selection->getField('description')]);
        $media = $selection->filter('media');
        if ($media && !empty($data[$media->getField('uuid')])) {
            $productMedia->setMedia(
                $this->mediaFactory->hydrate($data, new MediaBasicStruct(), $media, $context)
            );
        }

        /** @var $extension ProductMediaExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($productMedia, $data, $selection, $context);
        }

        return $productMedia;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['media'] = $this->mediaFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        if ($media = $selection->filter('media')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'media',
                $media->getRootEscaped(),
                sprintf('%s.uuid = %s.media_uuid', $media->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->mediaFactory->joinDependencies($media, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'product_media_translation',
                $translation->getRootEscaped(),
                sprintf(
                    '%s.product_media_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                    $translation->getRootEscaped(),
                    $selection->getRootEscaped(),
                    $translation->getRootEscaped()
                )
            );
            $query->setParameter('languageUuid', $context->getShopUuid());
        }

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['media'] = $this->mediaFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }
}
