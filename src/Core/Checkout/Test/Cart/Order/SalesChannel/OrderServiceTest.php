<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OrderServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    private const FIXTURE_FILE = __DIR__ . '/../../../../../Content/Test/Media/fixtures/Shopware_5_3_Broschuere.pdf';

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $folderRepository;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var \League\Flysystem\FilesystemInterface
     */
    private $fileSystem;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->orderService = $this->getContainer()->get(OrderService::class);
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->folderRepository = $this->getContainer()->get('media_folder.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->mediaService = $this->getContainer()->get(MediaService::class);
        $this->fileSystem = $this->getContainer()->get('shopware.filesystem.private');
        $this->context = Context::createDefaultContext();
    }

    public function testGetAttachment(): void
    {
        $this->setFixtureContext($this->context);
        $mediaPdf = $this->getPdf();

        $this->mediaRepository->update([
            [
                'id' => $mediaPdf->getId(),
                'mediaFolderId' => $mediaPdf->getMediaFolderId(),
            ],
        ], $this->context);

        $filePath = $this->urlGenerator->getRelativeMediaUrl($mediaPdf);
        $this->getPublicFilesystem()->putStream($filePath, fopen(self::FIXTURE_FILE, 'rb'));

        $attachmentData = $this->orderService->getAttachment($mediaPdf, $this->context);
        static::assertArrayHasKey('content', $attachmentData);
        static::assertArrayHasKey('fileName', $attachmentData);
        static::assertArrayHasKey('mimeType', $attachmentData);
        static::assertEquals($mediaPdf->getFileName() . '.' . $mediaPdf->getFileExtension(), $attachmentData['fileName']);
        static::assertEquals($mediaPdf->getMimeType(), $attachmentData['mimeType']);
    }
}
