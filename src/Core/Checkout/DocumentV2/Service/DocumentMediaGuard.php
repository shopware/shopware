<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Service;

use Shopware\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentMediaGuard implements ResetInterface
{
    private ?string $folderId = null;

    private bool $folderIdResolved = false;

    /**
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(private readonly EntityRepository $mediaFolderRepository)
    {
    }

    public function reset(): void
    {
        $this->folderId = null;
        $this->folderIdResolved = false;
    }

    public function assertIsDocumentMedia(MediaEntity $media, Context $context): void
    {
        if ($media->getMediaFolderId() === $this->getFolderId($context)) {
            return;
        }

        throw DocumentV2Exception::documentMediaNotAllowed($media->getId());
    }

    public function getFolderId(Context $context): ?string
    {
        if ($this->folderIdResolved) {
            return $this->folderId;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', DocumentPersister::MEDIA_FOLDER))
            ->setLimit(1);

        $this->folderId = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): ?string => $this->mediaFolderRepository->searchIds($criteria, $scoped)->firstId(),
        );

        $this->folderIdResolved = true;

        return $this->folderId;
    }
}
