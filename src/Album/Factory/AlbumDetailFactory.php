<?php

namespace Shopware\Album\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Album\Struct\AlbumDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Media\Factory\MediaBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;

class AlbumDetailFactory extends AlbumBasicFactory
{
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

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        AlbumBasicStruct $album,
        QuerySelection $selection,
        TranslationContext $context
    ): AlbumBasicStruct {
        /** @var AlbumDetailStruct $album */
        $album = parent::hydrate($data, $album, $selection, $context);

        return $album;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($medias = $selection->filter('medias')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'media',
                $medias->getRootEscaped(),
                sprintf('%s.uuid = %s.album_uuid', $selection->getRootEscaped(), $medias->getRootEscaped())
            );

            $this->mediaFactory->joinDependencies($medias, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['medias'] = $this->mediaFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
