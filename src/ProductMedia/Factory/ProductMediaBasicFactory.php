<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Read\Factory;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\ProductMedia\Extension\ProductMediaExtension;
use Shopware\ProductMedia\Struct\ProductMediaBasicStruct;

class ProductMediaBasicFactory extends Factory
{
    const ROOT_NAME = 'product_media';
    const EXTENSION_NAMESPACE = 'productMedia';

    const FIELDS = [
       'uuid' => 'uuid',
       'productUuid' => 'product_uuid',
       'isCover' => 'is_cover',
       'position' => 'position',
       'mediaUuid' => 'media_uuid',
       'parentUuid' => 'parent_uuid',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
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
        $productMedia->setProductUuid((string) $data[$selection->getField('productUuid')]);
        $productMedia->setIsCover((bool) $data[$selection->getField('isCover')]);
        $productMedia->setPosition((int) $data[$selection->getField('position')]);
        $productMedia->setMediaUuid((string) $data[$selection->getField('mediaUuid')]);
        $productMedia->setParentUuid(isset($data[$selection->getField('parentUuid')]) ? (string) $data[$selection->getField('parentUuid')] : null);
        $productMedia->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $productMedia->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
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
        $this->joinMedia($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

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

    private function joinMedia(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($media = $selection->filter('media'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'media',
            $media->getRootEscaped(),
            sprintf('%s.uuid = %s.media_uuid', $media->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->mediaFactory->joinDependencies($media, $query, $context);
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
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
}
