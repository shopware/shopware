<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaVisibilityRestrictionSubscriber::class)]
class MediaVisibilityRestrictionSubscriberTest extends TestCase
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

    private Context $salesChannelContext;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->ids = new IdsCollection();

        $this->salesChannelContext = Context::createDefaultContext(new SalesChannelApiSource($this->ids->get('sales-channel')));
    }

    public function testSearchPublicMediaIsFound(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $result = $this->mediaRepository->search($criteria, $this->salesChannelContext);
        $mediaIds = array_map(fn ($media) => $media->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateMediaId, $mediaIds, 'Private media should not be found');
        static::assertContains($publicMediaInPrivateFolder, $mediaIds, 'Public media in private folder should be found');
        static::assertContains($publicMediaId, $mediaIds, 'Public media should be found');
    }

    public function testSearchPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addFilter(new EqualsFilter('private', true));
        $result = $this->mediaRepository->search($criteria, $this->salesChannelContext);
        $mediaIds = array_map(fn ($media) => $media->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateMediaId, $mediaIds, 'Private media should not be found');
        static::assertNotContains($publicMediaId, $mediaIds, 'Public media should not be found when filtering for private');
        static::assertCount(0, $mediaIds, 'No media should be returned when searching for private media');
    }

    public function testSearchPublicMediaFolderIsFound(): void
    {
        $privateFolderId = $this->createMediaFolder('private-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-folder', 'public-folder', false);

        $criteria = new Criteria([
            $privateFolderId,
            $publicFolderId,
        ]);
        $result = $this->mediaFolderRepository->search($criteria, $this->salesChannelContext);
        $folderIds = array_map(fn ($folder) => $folder->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateFolderId, $folderIds, 'Private folder should not be found');
        static::assertContains($publicFolderId, $folderIds, 'Public folder should be found');
    }

    public function testSearchPrivateMediaFolderIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-folder', 'public-folder', false);

        $criteria = new Criteria([
            $privateFolderId,
            $publicFolderId,
        ]);
        $criteria->addFilter(new EqualsFilter('configuration.private', true));
        $result = $this->mediaFolderRepository->search($criteria, $this->salesChannelContext);
        $folderIds = array_map(fn ($folder) => $folder->getId(), $result->getEntities()->getElements());
        static::assertNotContains($privateFolderId, $folderIds, 'Private folder should not be found');
        static::assertNotContains($publicFolderId, $folderIds, 'Public folder should not be found when filtering for private');
        static::assertCount(0, $folderIds, 'No folder should be returned when searching for private folders');
    }

    public function testFilterAggregationForPublicMediaIsExecuted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new FilterAggregation(
                'public-media',
                new CountAggregation('public-media-count', 'id'),
                [new EqualsFilter('private', false)]
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->salesChannelContext);
        static::assertCount(2, $result);

        static::assertTrue($result->has($this->ids->get('public-media.1')));
        static::assertTrue($result->has($this->ids->get('public-media.2')));

        $countResult = $result->getAggregations()->get('public-media-count');
        static::assertInstanceOf(CountResult::class, $countResult);
        static::assertSame(2, $countResult->getCount(), 'Public media should be counted in aggregation');
    }

    public function testFilterAggregationForPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new FilterAggregation(
                'private-media',
                new CountAggregation('private-media-count', 'id'),
                [new EqualsFilter('private', true)]
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->salesChannelContext);
        $countResult = $result->getAggregations()->get('private-media-count');
        static::assertInstanceOf(CountResult::class, $countResult);
        static::assertSame(0, $countResult->getCount(), 'No private media should be counted in aggregation');
    }

    public function testOtherAggregationForPrivateMediaIsRestricted(): void
    {
        $privateFolderId = $this->createMediaFolder('private-media-folder', 'private-folder', true);
        $publicFolderId = $this->createMediaFolder('public-media-folder', 'public-folder', false);

        $privateMediaId = $this->createMedia('private-media', 'private-file', $privateFolderId, true);
        $publicMediaId = $this->createMedia('public-media.1', 'public-file-1', $publicFolderId, false);
        $publicMediaInPrivateFolder = $this->createMedia('public-media.2', 'public-file-2', $privateFolderId, false);

        $criteria = new Criteria([
            $privateMediaId,
            $publicMediaId,
            $publicMediaInPrivateFolder,
        ]);
        $criteria->addAggregation(
            new TermsAggregation(
                'private-media-terms',
                'private'
            )
        );
        $result = $this->mediaRepository->search($criteria, $this->salesChannelContext);
        $termsResult = $result->getAggregations()->get('private-media-terms');
        static::assertInstanceOf(TermsResult::class, $termsResult);
        $buckets = $termsResult->getBuckets();
        $bucketValues = array_map(fn (Bucket $b) => $b->getKey(), $buckets);
        static::assertNotContains('1', $bucketValues, 'There should be no bucket for private media');
        static::assertContains('0', $bucketValues, 'There should be a bucket for public media');

        $publicBucket = array_filter($buckets, fn (Bucket $b) => $b->getKey() === '0');
        static::assertCount(1, $publicBucket, 'There should be exactly one public media bucket');
        $publicCount = $publicBucket[0];
        static::assertInstanceOf(Bucket::class, $publicCount);
        static::assertSame(2, $publicCount->getCount(), 'Public media bucket should have count 2');
    }

    private function createMediaFolder(string $key, string $name, bool $isPrivate): string
    {
        $id = $this->ids->get($key);
        $this->mediaFolderRepository->create([
            [
                'id' => $this->ids->get($key),
                'name' => $name,
                'useParentConfiguration' => false,
                'configuration' => [
                    'private' => $isPrivate,
                ],
            ],
        ], $this->salesChannelContext);

        return $id;
    }

    private function createMedia(string $key, string $fileName, string $folderId, bool $isPrivate): string
    {
        $id = $this->ids->get($key);
        $this->mediaRepository->create([
            [
                'id' => $id,
                'mediaFolderId' => $folderId,
                'private' => $isPrivate,
                'fileName' => $fileName,
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
        ], $this->salesChannelContext);

        return $id;
    }
}
