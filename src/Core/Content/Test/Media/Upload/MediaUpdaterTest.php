<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Upload;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Memory\MemoryAdapter;
use Shopware\Core\Content\Media\Upload\MediaUpdater;
use Shopware\Core\Content\Media\Util\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MediaUpdaterTest extends KernelTestCase
{
    const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var MediaUpdater
     */
    private $mediaUpdater;

    /** @var FilesystemInterface */
    private $filesystem;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function setUp()
    {
        self::bootKernel();
        $this->repository = self::$container->get('media.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->filesystem = new Filesystem(new MemoryAdapter());
        $this->urlGenerator = self::$container->get(UrlGeneratorInterface::class);

        // create media updater with in memory filesystem, so we do not need to clean up files afterwards
        $this->mediaUpdater = new MediaUpdater(
            $this->repository,
            $this->filesystem,
            $this->urlGenerator,
            $this->createMock(EventDispatcherInterface::class)
        );

        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testPersistFileToMedia()
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');

        file_put_contents($tempFile, file_get_contents(self::TEST_IMAGE));

        $mimeType = 'image/png';
        $fileSize = filesize($tempFile);
        $mediaId = Uuid::uuid4();

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $context->getExtension('write_protection')->set('write_media', true);

        $this->repository->create(
            [
                [
                    'id' => $mediaId->getHex(),
                    'name' => 'test file',
                ],
            ],
            $context
        );

        try {
            $this->mediaUpdater->persistFileToMedia(
                $tempFile, $mediaId->getHex(),
                $mimeType,
                'png',
                $fileSize,
                $context);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $path = $this->urlGenerator->getMediaUrl($mediaId->getHex(), 'png', false);

        static::assertTrue($this->filesystem->has($path));
    }
}
