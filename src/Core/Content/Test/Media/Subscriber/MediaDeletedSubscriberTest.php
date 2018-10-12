<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Subscriber;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Pathname\UrlGenerator;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaDeletedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

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

    /** @var UrlGenerator */
    private $urlGenerator;

    /** @var FilesystemInterface */
    private $filesystem;

    public function setUp()
    {
        $this->fileSaver = $this->getContainer()->get(FileSaver::class);
        $this->repository = $this->getContainer()->get('media.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->filesystem = $this->getContainer()->get('shopware.filesystem.public');
    }

    public function testDeleteSubscriber(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::uuid4();

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
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
                $mediaFile,
                $mediaId->getHex(),
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $url = $this->urlGenerator->getRelativeMediaUrl($mediaId->getHex(), 'png');

        static::assertTrue($this->filesystem->has($url));

        $this->repository->delete([['id' => $mediaId->getHex()]], $context);

        static::assertFalse($this->filesystem->has($url));
    }
}
