<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Subscriber;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Upload\MediaUpdater;
use Shopware\Core\Content\Media\Util\MimeType;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MediaDeletedSubscriberTest extends KernelTestCase
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

    /** @var StrategyInterface */
    private $strategy;

    /** @var FilesystemInterface */
    private $filesystem;

    public function setUp()
    {
        self::bootKernel();
        $this->mediaUpdater = self::$container->get(MediaUpdater::class);
        $this->repository = self::$container->get('media.repository');
        $this->connection = self::$container->get(Connection::class);
        $this->strategy = self::$container->get(StrategyInterface::class);
        $this->filesystem = self::$container->get('shopware.filesystem.public');
        $this->connection->beginTransaction();
    }

    public function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testDeleteSubscriber()
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
            $this->mediaUpdater->persistFileToMedia($tempFile, $mediaId->getHex(), $mimeType, $fileSize, $context);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $path = $this->strategy->encode($mediaId->getHex());

        static::assertTrue($this->filesystem->has('media/' . $path . MimeType::getExtension($mimeType)));

        $this->repository->delete([['id' => $mediaId->getHex()]], $context);

        static::assertFalse($this->filesystem->has('media/' . $path . MimeType::getExtension($mimeType)));
    }
}
