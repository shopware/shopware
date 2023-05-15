<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Message;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsHandler;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class GenerateThumbnailsHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @var EntityRepository
     */
    private $thumbnailRepository;

    private Context $context;

    /**
     * @var GenerateThumbnailsHandler
     */
    private $handler;

    protected function setUp(): void
    {
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailRepository = $this->getContainer()->get('media_thumbnail.repository');
        $this->context = Context::createDefaultContext();

        $this->handler = $this->getContainer()->get(GenerateThumbnailsHandler::class);
    }

    public function testGenerateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
            [
                'mediaId' => $media->getId(),
                'width' => 150,
                'height' => 150,
            ],
        ], $this->context);

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $msg = new GenerateThumbnailsMessage();
        $msg->setMediaIds([$media->getId()]);

        if (Feature::isActive('v6.6.0.0')) {
            $msg->setContext($this->context);
        } else {
            $msg->withContext($this->context);
        }

        $this->handler->__invoke($msg);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());
        $mediaThumbnailCollection = $media->getThumbnails();
        static::assertNotNull($mediaThumbnailCollection);
        static::assertEquals(2, $mediaThumbnailCollection->count());

        foreach ($mediaThumbnailCollection as $thumbnail) {
            static::assertTrue(
                ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
                || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150)
            );

            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );
        }
    }

    public function testUpdateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
        ], $this->context);

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $this->getPublicFilesystem()->writeStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'rb')
        );

        $msg = new UpdateThumbnailsMessage();
        $msg->setMediaIds([$media->getId()]);

        if (Feature::isActive('v6.6.0.0')) {
            $msg->setContext($this->context);
        } else {
            $msg->withContext($this->context);
        }

        $this->handler->__invoke($msg);

        $criteria = new Criteria([$media->getId()]);
        $criteria->addAssociation('thumbnails');
        $criteria->addAssociation('mediaFolder.configuration.thumbnailSizes');

        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search($criteria, $this->context)->get($media->getId());
        $mediaThumbnailCollection = $media->getThumbnails();
        static::assertNotNull($mediaThumbnailCollection);
        static::assertEquals(2, $mediaThumbnailCollection->count());

        foreach ($mediaThumbnailCollection as $thumbnail) {
            static::assertTrue(
                ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300)
                || ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150)
            );

            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail);
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist'
            );
        }
    }

    public function testDiffersBetweenUpdateAndGenerateMessage(): void
    {
        $thumbnailServiceMock = $this->getMockBuilder(ThumbnailService::class)
            ->disableOriginalConstructor()->getMock();

        $handler = new GenerateThumbnailsHandler($thumbnailServiceMock, $this->mediaRepository);

        $randomCriteria = (new Criteria())
            /* @see GenerateThumbnailsHandler Association as in target method is required for the ease of PHPUnit's constraint evaluation */
            ->addAssociation('mediaFolder.configuration.mediaThumbnailSizes')
            ->setLimit(5);

        $testEntities1 = $this->mediaRepository->search($randomCriteria->setOffset(0), $this->context)->getEntities();
        $testEntities2 = $this->mediaRepository->search($randomCriteria->setOffset(5), $this->context)->getEntities();
        $testEntities3 = $this->mediaRepository->search($randomCriteria->setOffset(10), $this->context)->getEntities();

        $generateMessage = new GenerateThumbnailsMessage();
        $generateMessage->setMediaIds($testEntities1->getIds());

        if (Feature::isActive('v6.6.0.0')) {
            $generateMessage->setContext($this->context);
        } else {
            $generateMessage->withContext($this->context);
        }

        $updateMessage1 = new UpdateThumbnailsMessage();
        $updateMessage1->setMediaIds($testEntities2->getIds());
        $updateMessage1->setIsStrict(true);

        if (Feature::isActive('v6.6.0.0')) {
            $updateMessage1->setContext($this->context);
        } else {
            $updateMessage1->withContext($this->context);
        }

        $updateMessage2 = new UpdateThumbnailsMessage();
        $updateMessage2->setMediaIds($testEntities3->getIds());
        $updateMessage2->setIsStrict(false);

        if (Feature::isActive('v6.6.0.0')) {
            $updateMessage2->setContext($this->context);
        } else {
            $updateMessage2->withContext($this->context);
        }

        $thumbnailServiceMock->expects(static::once())
            ->method('generate')
            ->with($testEntities1, $this->context)
            ->willReturn($testEntities1->count());

        $consecutiveUpdateMessageParams = [
            // For UpdateMessage 1
            ...array_map(fn ($entity) => [$entity, $this->context, true], array_values($testEntities2->getElements())),
            // For UpdateMessage 2
            ...array_map(fn ($entity) => [$entity, $this->context, false], array_values($testEntities3->getElements())),
        ];

        $parameters = [];

        $thumbnailServiceMock->expects(static::exactly($testEntities2->count() + $testEntities3->count()))
            ->method('updateThumbnails')
            ->willReturnCallback(function (...$params) use (&$parameters): void {
                $parameters[] = $params;
            });

        $handler->__invoke($generateMessage);
        $handler->__invoke($updateMessage1);
        $handler->__invoke($updateMessage2);

        static::assertEquals($consecutiveUpdateMessageParams, $parameters);
    }
}
