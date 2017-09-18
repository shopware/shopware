<?php

namespace Shopware\Media\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Factory\AlbumBasicFactory;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\Factory;
use Shopware\Media\Extension\MediaExtension;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class MediaBasicFactory extends Factory
{
    const ROOT_NAME = 'media';

    const FIELDS = [
       'uuid' => 'uuid',
       'album_uuid' => 'album_uuid',
       'file_name' => 'file_name',
       'mime_type' => 'mime_type',
       'file_size' => 'file_size',
       'meta_data' => 'meta_data',
       'user_uuid' => 'user_uuid',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
       'name' => 'translation.name',
       'description' => 'translation.description',
    ];

    /**
     * @var MediaExtension[]
     */
    protected $extensions = [];

    /**
     * @var AlbumBasicFactory
     */
    protected $albumFactory;

    public function __construct(
        Connection $connection,
        array $extensions,
        AlbumBasicFactory $albumFactory
    ) {
        parent::__construct($connection, $extensions);
        $this->albumFactory = $albumFactory;
    }

    public function hydrate(
        array $data,
        MediaBasicStruct $media,
        QuerySelection $selection,
        TranslationContext $context
    ): MediaBasicStruct {
        $media->setUuid((string) $data[$selection->getField('uuid')]);
        $media->setAlbumUuid((string) $data[$selection->getField('album_uuid')]);
        $media->setFileName((string) $data[$selection->getField('file_name')]);
        $media->setMimeType((string) $data[$selection->getField('mime_type')]);
        $media->setFileSize((int) $data[$selection->getField('file_size')]);
        $media->setMetaData(isset($data[$selection->getField('meta_data')]) ? (string) $data[$selection->getField('meta_data')] : null);
        $media->setUserUuid(isset($data[$selection->getField('user_uuid')]) ? (string) $data[$selection->getField('user_uuid')] : null);
        $media->setCreatedAt(new \DateTime($data[$selection->getField('created_at')]));
        $media->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
        $media->setName((string) $data[$selection->getField('name')]);
        $media->setDescription(isset($data[$selection->getField('description')]) ? (string) $data[$selection->getField('description')] : null);
        $album = $selection->filter('album');
        if ($album && !empty($data[$album->getField('uuid')])) {
            $media->setAlbum(
                $this->albumFactory->hydrate($data, new AlbumBasicStruct(), $album, $context)
            );
        }

        foreach ($this->extensions as $extension) {
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
        if ($album = $selection->filter('album')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'album',
                $album->getRootEscaped(),
                sprintf('%s.uuid = %s.album_uuid', $album->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->albumFactory->joinDependencies($album, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
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
}
