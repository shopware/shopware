<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Api\MediaUploadController;
use Shopware\Core\Content\Media\Event\MediaUploadedEvent;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(MediaUploadController::class)]
#[Group('needsWebserver')]
class MediaUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    final public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    private EntityRepository $mediaRepository;

    private string $mediaId;

    private Context $context;

    private bool $mediaDirCreated = false;

    private ?MediaUploadedEvent $thrownMediaEvent;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $this->context = Context::createDefaultContext();
        $this->mediaId = $this->getEmptyMedia()->getId();
        $this->thrownMediaEvent = null;

        $this->addEventListener(
            $this->getContainer()->get('event_dispatcher'),
            MediaUploadedEvent::class,
            function (MediaUploadedEvent $event): void {
                $this->thrownMediaEvent = $event;
            }
        );

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        if (!\is_dir($projectDir . '/public/media')) {
            mkdir($projectDir . '/public/media');
            $this->mediaDirCreated = true;
        }
        \copy(self::TEST_IMAGE, $this->getContainer()->getParameter('kernel.project_dir') . '/public/media/shopware-logo.png');
    }

    protected function tearDown(): void
    {
        \unlink($this->getContainer()->getParameter('kernel.project_dir') . '/public/media/shopware-logo.png');

        if ($this->mediaDirCreated) {
            rmdir($this->getContainer()->getParameter('kernel.project_dir') . '/public/media');
            $this->mediaDirCreated = false;
        }
    }

    public function testUploadFromBinaryUsesMediaId(): void
    {
        $url = \sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
            'POST',
            $url . '?extension=png',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            (string) file_get_contents(self::TEST_IMAGE)
        );
        $media = $this->getMediaEntity();

        $mediaPath = $media->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertStringEndsWith($media->getId() . '.' . $media->getFileExtension(), $mediaPath);

        $this->assertMediaApiResponse();
    }

    public function testUploadFromBinaryUsesFileName(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, MediaUploadedEvent::class, $listener);

        $url = \sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
            'POST',
            $url . '?extension=png&fileName=new%20file%20name',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            (string) file_get_contents(self::TEST_IMAGE)
        );
        $media = $this->getMediaEntity();

        $mediaPath = $media->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertIsString($media->getFileName());
        static::assertStringEndsWith('new file name', $media->getFileName());

        $this->assertMediaApiResponse();
    }

    public function testUploadFromURL(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, MediaUploadedEvent::class, $listener);

        $baseUrl = EnvironmentHelper::getVariable('APP_URL') . '/media/shopware-logo.png';

        $url = \sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
            'POST',
            $url . '?extension=png',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'application/json',
            ],
            json_encode(['url' => $baseUrl], \JSON_THROW_ON_ERROR)
        );
        $response = $this->getBrowser()->getResponse();

        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $location = $response->headers->get('location');
        static::assertIsString($location);
        static::assertStringEndsWith(
            '/api/media/' . $this->mediaId,
            $location
        );

        $path = $media->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($path));

        $this->assertMediaApiResponse();
    }

    public function testRenameMediaFileThrowsExceptionIfFileNameIsNotPresent(): void
    {
        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::never())->method('__invoke');
        $this->addEventListener($dispatcher, MediaUploadedEvent::class, $listener);

        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = \sprintf(
            '/api/_action/media/%s/rename',
            $media->getId()
        );

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode([], \JSON_THROW_ON_ERROR)
        );

        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(400, $response->getStatusCode());
        static::assertEquals('CONTENT__MEDIA_EMPTY_FILE_NAME', $responseData['errors'][0]['code']);

        static::assertNull($this->thrownMediaEvent);
    }

    public function testRenameMediaFileHappyPath(): void
    {
        $context = Context::createDefaultContext();

        $ids = new IdsCollection();
        $data = [
            'id' => $id = $ids->get('media'),
            'fileName' => 'original_file_name',
            'path' => 'media/original_file_name.png',
            'fileExtension' => 'png',
        ];

        $this->mediaRepository->create([$data], $context);
        $media = $this->mediaRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertNotEmpty($media->getPath());

        $this->getPublicFilesystem()->write($media->getPath(), 'some content');

        $url = \sprintf(
            '/api/_action/media/%s/rename',
            $media->getId()
        );

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['fileName' => 'new_file_name'], \JSON_THROW_ON_ERROR)
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $updated = $this->mediaRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(MediaEntity::class, $updated);
        static::assertNotEquals($media->getFileName(), $updated->getFileName());

        static::assertTrue($this->getPublicFilesystem()->has($updated->getPath()));
        static::assertFalse($this->getPublicFilesystem()->has($media->getPath()));
    }

    public function testProvideName(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = \sprintf(
            '/api/_action/media/provide-name?fileName=%s&extension=png',
            $media->getFileName()
        );

        $this->getBrowser()->request(
            'GET',
            $url
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($media->getFileName() . '_(1)', $result['fileName']);
    }

    public function testProvideNameProvidesOwnName(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = \sprintf(
            '/api/_action/media/provide-name?fileName=%s&extension=png&mediaId=%s',
            $media->getFileName(),
            $media->getId()
        );

        $this->getBrowser()->request(
            'GET',
            $url
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertEquals($media->getFileName(), $result['fileName']);
    }

    private function getMediaEntity(): MediaEntity
    {
        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
        $location = $response->headers->get('Location');
        static::assertIsString($location);
        static::assertStringEndsWith(
            '/api/media/' . $this->mediaId,
            $location
        );

        return $media;
    }

    private function assertMediaApiResponse(): void
    {
        $this->getBrowser()->request(
            'GET',
            '/api/media/' . $this->mediaId
        );

        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(
            4,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            499,
            $responseData['data']['attributes']['metaData']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertCount(
            3,
            $responseData['data']['attributes']['mediaType'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertSame(
            'IMAGE',
            $responseData['data']['attributes']['mediaType']['name'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertCount(
            1,
            $responseData['data']['attributes']['mediaType']['flags'],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
        static::assertSame(
            ImageType::TRANSPARENT,
            $responseData['data']['attributes']['mediaType']['flags'][0],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
        $this->assertMediaEventThrown();
    }

    private function assertMediaEventThrown(): void
    {
        static::assertNotNull($this->thrownMediaEvent);
        static::assertEquals($this->mediaId, $this->thrownMediaEvent->getMediaId());
    }
}
