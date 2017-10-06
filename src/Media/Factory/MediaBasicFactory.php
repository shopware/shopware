<?php declare(strict_types=1);

namespace Shopware\Media\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Factory\AlbumBasicFactory;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\Media\Extension\MediaExtension;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class MediaBasicFactory extends Factory
{
    const ROOT_NAME = 'media';
    const EXTENSION_NAMESPACE = 'media';

    const FIELDS = [
       'uuid' => 'uuid',
       'albumUuid' => 'album_uuid',
       'fileName' => 'file_name',
       'mimeType' => 'mime_type',
       'fileSize' => 'file_size',
       'metaData' => 'meta_data',
       'userUuid' => 'user_uuid',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
       'name' => 'translation.name',
       'description' => 'translation.description',
    ];

    /**
     * @var AlbumBasicFactory
     */
    protected $albumFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        AlbumBasicFactory $albumFactory
    ) {
        parent::__construct($connection, $registry);
        $this->albumFactory = $albumFactory;
    }

    public function hydrate(
        array $data,
        MediaBasicStruct $media,
        QuerySelection $selection,
        TranslationContext $context
    ): MediaBasicStruct {
        $media->setUuid((string) $data[$selection->getField('uuid')]);
        $media->setAlbumUuid((string) $data[$selection->getField('albumUuid')]);
        $media->setFileName((string) $data[$selection->getField('fileName')]);
        $media->setMimeType((string) $data[$selection->getField('mimeType')]);
        $media->setFileSize((int) $data[$selection->getField('fileSize')]);
        $media->setMetaData(isset($data[$selection->getField('metaData')]) ? (string) $data[$selection->getField('metaData')] : null);
        $media->setUserUuid(isset($data[$selection->getField('userUuid')]) ? (string) $data[$selection->getField('userUuid')] : null);
        $media->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $media->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $media->setName((string) $data[$selection->getField('name')]);
        $media->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $album = $selection->filter('album');
        if ($album && !empty($data[$album->getField('uuid')])) {
            $media->setAlbum(
                $this->albumFactory->hydrate($data, new AlbumBasicStruct(), $album, $context)
            );
        }

        /** @var $extension MediaExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($media, $data, $selection, $context);
        }

        return $media;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['album'] = $this->albumFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinAlbum($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['album'] = $this->albumFactory->getAllFields();

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

    private function joinAlbum(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($album = $selection->filter('album'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'album',
            $album->getRootEscaped(),
            sprintf('%s.uuid = %s.album_uuid', $album->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->albumFactory->joinDependencies($album, $query, $context);
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
            'media_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.media_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
