<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class MediaVisibilityRestrictionSubscriberProductDocumentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Context $context;

    private Context $salesChannelContext;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->context = Context::createDefaultContext();
        $this->salesChannelContext = Context::createDefaultContext(new SalesChannelApiSource(Uuid::randomHex()));
    }

    public function testPrivateProductDocumentMediaIsVisibleForSalesChannelContext(): void
    {
        $folderId = $this->getProductDocumentFolderId();
        $mediaId = Uuid::randomHex();

        $this->createMedia($mediaId, $folderId, true);

        $mediaResult = $this->mediaRepository->search(new Criteria([$mediaId]), $this->salesChannelContext);
        static::assertTrue($mediaResult->has($mediaId));

        $folderResult = $this->mediaFolderRepository->search(new Criteria([$folderId]), $this->salesChannelContext);
        static::assertFalse($folderResult->has($folderId));
    }

    public function testPrivateMediaInRegularPrivateFolderIsStillRestricted(): void
    {
        $folderId = Uuid::randomHex();
        $mediaId = Uuid::randomHex();

        $this->mediaFolderRepository->create([[
            'id' => $folderId,
            'name' => 'regular-private-folder-' . $folderId,
            'useParentConfiguration' => false,
            'configuration' => [
                'private' => true,
            ],
        ]], $this->context);
        $this->createMedia($mediaId, $folderId, true);

        $mediaResult = $this->mediaRepository->search(new Criteria([$mediaId]), $this->salesChannelContext);
        static::assertFalse($mediaResult->has($mediaId));

        $folderResult = $this->mediaFolderRepository->search(new Criteria([$folderId]), $this->salesChannelContext);
        static::assertFalse($folderResult->has($folderId));
    }

    private function getProductDocumentFolderId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('defaultFolder.entity', 'product_document'));
        $criteria->setLimit(1);

        $folderId = $this->mediaFolderRepository->searchIds($criteria, $this->context)->firstId();

        static::assertIsString($folderId);

        return $folderId;
    }

    private function createMedia(string $mediaId, string $folderId, bool $private): void
    {
        $this->mediaRepository->create([[
            'id' => $mediaId,
            'mediaFolderId' => $folderId,
            'private' => $private,
            'fileName' => 'manual-' . $mediaId,
            'fileExtension' => 'pdf',
            'mimeType' => 'application/pdf',
        ]], $this->context);
    }
}
