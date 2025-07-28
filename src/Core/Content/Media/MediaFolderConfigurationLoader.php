<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MediaFolderConfigurationLoader extends AbstractMediaFolderConfigurationLoader
{
    public function __construct(
        #[Autowire(service: 'media_folder_configuration.repository')]
        private readonly EntityRepository $repository
    ) {
    }

    public function load(string $mediaFolderId, ?Context $context = null): ?MediaFolderConfigurationEntity
    {
        $context ??= Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaFolders.id', $mediaFolderId));

        return $this->repository->search($criteria, $context)->first();
    }
}
