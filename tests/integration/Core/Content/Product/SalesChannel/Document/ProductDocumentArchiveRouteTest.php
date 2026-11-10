<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Document;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[Group('store-api')]
class ProductDocumentArchiveRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testAVariantGetsTheDocumentsOfItsParentRebuiltAfterChanges(): void
    {
        $ids = new IdsCollection();
        $browser = $this->createCustomSalesChannelBrowser(['id' => $ids->create('sales-channel')]);
        static::getContainer()->get(RateLimiter::class)->reset(RateLimiter::PRODUCT_DOCUMENTS_ARCHIVE, '127.0.0.1');

        $context = Context::createDefaultContext();
        $manual = 'manual-' . Uuid::randomHex();
        $sheet = 'data-sheet-' . Uuid::randomHex();
        $mediaService = static::getContainer()->get(MediaService::class);
        $manualId = $mediaService->saveFile('manual content', 'pdf', 'application/pdf', $manual, $context, private: false);
        $sheetId = $mediaService->saveFile('sheet content', 'pdf', 'application/pdf', $sheet, $context, private: false);

        $drill = (new ProductBuilder($ids, 'drill'))
            ->price(10)
            ->visibility($ids->get('sales-channel'))
            ->variant((new ProductBuilder($ids, 'drill-red'))->build())
            ->build();
        $drill['documents'] = [
            ['title' => 'Manual', 'mediaId' => $manualId, 'position' => 1],
            ['id' => $ids->create('sheet'), 'title' => 'Data sheet', 'mediaId' => $sheetId, 'position' => 2],
        ];
        static::getContainer()->get('product.repository')->create([$drill], $context);

        static::assertSame(
            [$manual . '.pdf' => 'manual content', $sheet . '.pdf' => 'sheet content'],
            $this->download($browser, $ids->get('drill-red'))
        );

        $archives = static::getContainer()->get('shopware.filesystem.private');
        static::assertInstanceOf(FilesystemOperator::class, $archives);
        $folder = ProductDocumentArchiver::DIRECTORY . '/' . $ids->get('drill');
        static::assertCount(1, $archives->listContents($folder)->toArray());

        $handbook = 'handbook-' . Uuid::randomHex();
        static::getContainer()->get(FileSaver::class)->renameMedia($manualId, $handbook, $context);

        static::assertSame([], $archives->listContents($folder)->toArray());
        static::assertSame(
            [$handbook . '.pdf' => 'manual content', $sheet . '.pdf' => 'sheet content'],
            $this->download($browser, $ids->get('drill-red'))
        );

        $newSheet = 'data-sheet-' . Uuid::randomHex();
        $newSheetId = $mediaService->saveFile('new sheet content', 'pdf', 'application/pdf', $newSheet, $context, private: false);
        static::getContainer()->get('product_document.repository')->update([['id' => $ids->get('sheet'), 'mediaId' => $newSheetId]], $context);

        static::assertSame([], $archives->listContents($folder)->toArray());
        static::assertSame(
            [$handbook . '.pdf' => 'manual content', $newSheet . '.pdf' => 'new sheet content'],
            $this->download($browser, $ids->get('drill-red'))
        );

        $archives->deleteDirectory($folder);
    }

    /**
     * @return array<string, string>
     */
    private function download(KernelBrowser $browser, string $productId): array
    {
        $browser->request('GET', '/store-api/product/' . $productId . '/documents/archive');
        $content = $browser->getInternalResponse()->getContent();

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame('application/zip', $browser->getResponse()->headers->get('Content-Type'));

        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'archive-test-', '.zip');
        $filesystem->dumpFile($path, $content);

        $zip = new \ZipArchive();
        static::assertTrue($zip->open($path));

        $entries = [];
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $entries[(string) $zip->getNameIndex($index)] = (string) $zip->getFromIndex($index);
        }

        $zip->close();
        $filesystem->remove($path);

        return $entries;
    }
}
