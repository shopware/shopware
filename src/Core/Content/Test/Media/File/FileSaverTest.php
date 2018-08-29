<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FileSaverTest extends TestCase
{
    use IntegrationTestBehaviour;

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
     * @var FileSaver
     */
    private $fileSaver;

    /** @var FilesystemInterface */
    private $filesystem;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    public function setUp()
    {
        $this->repository = $this->getContainer()->get('media.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->filesystem = new Filesystem(new MemoryAdapter());
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        // create media updater with in memory filesystem, so we do not need to clean up files afterwards
        $this->fileSaver = new FileSaver(
            $this->repository,
            $this->filesystem,
            $this->urlGenerator,
            $this->createMock(EventDispatcherInterface::class)
        );
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
            $this->fileSaver->persistFileToMedia(
                $tempFile,
                $mediaId->getHex(),
                $mimeType,
                'png',
                $fileSize,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $path = $this->urlGenerator->getRelativeMediaUrl($mediaId->getHex(), 'png');

        static::assertTrue($this->filesystem->has($path));
    }
}
