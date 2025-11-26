<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaVideoCoverController;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class MediaVideoCoverControllerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->setFixtureContext($this->context);

        /** @var EntityRepository<MediaCollection> $mediaRepository */
        $mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaRepository = $mediaRepository;
    }

    public function testAssignVideoCoverUpdatesMetaData(): void
    {
        $cover = $this->getPng();
        $video = $this->createVideoMedia();

        $response = $this->getController()->assignVideoCover(
            $video->getId(),
            new Request([], ['coverMediaId' => $cover->getId()]),
            $this->context,
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $reloaded = $this->getMediaEntity($video->getId());
        static::assertSame($cover->getId(), $reloaded->getMetaData()['video']['coverMediaId'] ?? null);
    }

    public function testAssignVideoCoverReturnsBadRequestOnInvalidPayload(): void
    {
        $video = $this->createVideoMedia();

        $response = $this->getController()->assignVideoCover(
            $video->getId(),
            new Request([], ['coverMediaId' => ['invalid']]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    private function getController(): MediaVideoCoverController
    {
        return static::getContainer()->get(MediaVideoCoverController::class);
    }

    private function createVideoMedia(): MediaEntity
    {
        $id = Uuid::randomHex();

        $this->mediaRepository->create([[
            'id' => $id,
            'mimeType' => 'video/mp4',
            'fileExtension' => 'mp4',
            'fileName' => 'video-' . $id,
            'fileSize' => 1024,
            'mediaType' => new VideoType(),
        ]], $this->context);

        return $this->getMediaEntity($id);
    }

    private function getMediaEntity(string $id): MediaEntity
    {
        /** @var MediaEntity|null $entity */
        $entity = $this->mediaRepository->search(new Criteria([$id]), $this->context)->first();

        if ($entity === null) {
            throw new \RuntimeException(\sprintf('Media entity "%s" not found', $id));
        }

        return $entity;
    }
}
