<?php declare(strict_types=1);

namespace Shopware\Album\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Album\Struct\AlbumBasicStruct;
use Shopware\Album\Struct\AlbumDetailStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Read\ExtensionRegistryInterface;
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

        $this->joinMedia($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['media'] = $this->mediaFactory->getAllFields();

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
            sprintf('%s.uuid = %s.album_uuid', $selection->getRootEscaped(), $media->getRootEscaped())
        );

        $this->mediaFactory->joinDependencies($media, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }
}
