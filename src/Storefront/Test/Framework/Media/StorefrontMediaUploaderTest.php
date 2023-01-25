<?php
declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\IllegalFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Storefront\Framework\Media\Exception\FileTypeNotAllowedException;
use Shopware\Storefront\Framework\Media\Exception\MediaValidatorMissingException;
use Shopware\Storefront\Framework\Media\StorefrontMediaUploader;
use Shopware\Storefront\Framework\Media\StorefrontMediaValidatorRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Package('content')]
class StorefrontMediaUploaderTest extends TestCase
{
    use KernelTestBehaviour;

    final public const FIXTURE_DIR = __DIR__ . '/fixtures';

    public function testUploadDocument(): void
    {
        $file = $this->getUploadFixture('empty.pdf');
        $result = $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());

        $repo = $this->getContainer()->get('media.repository');
        static::assertEquals(1, $repo->search(new Criteria([$result]), Context::createDefaultContext())->getTotal());
        $this->removeMedia($result);
    }

    public function testUploadDocumentFailIllegalFileType(): void
    {
        $this->expectException(FileTypeNotAllowedException::class);
        $this->expectExceptionMessage((new FileTypeNotAllowedException(
            'application/vnd.ms-excel',
            'documents'
        ))->getMessage());

        $file = $this->getUploadFixture('empty.xls');
        $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());
    }

    public function testUploadDocumentFailFilenameContainsPhp(): void
    {
        $this->expectException(IllegalFileNameException::class);
        $this->expectExceptionMessage(
            (new IllegalFileNameException('contains.php.pdf', 'contains PHP related file extension'))->getMessage()
        );

        $file = $this->getUploadFixture('contains.php.pdf');
        $this->getUploadService()->upload($file, 'test', 'documents', Context::createDefaultContext());
    }

    public function testUploadImage(): void
    {
        $file = $this->getUploadFixture('image.png');
        $result = $this->getUploadService()->upload($file, 'test', 'images', Context::createDefaultContext());

        $repo = $this->getContainer()->get('media.repository');
        static::assertEquals(1, $repo->search(new Criteria([$result]), Context::createDefaultContext())->getTotal());
        $this->removeMedia($result);
    }

    public function testUploadDocumentFailIllegalImageType(): void
    {
        $this->expectException(FileTypeNotAllowedException::class);
        $this->expectExceptionMessage((new FileTypeNotAllowedException(
            'image/webp',
            'images'
        ))->getMessage());

        $file = $this->getUploadFixture('image.webp');
        $this->getUploadService()->upload($file, 'test', 'images', Context::createDefaultContext());
    }

    public function testUploadUnknownType(): void
    {
        $this->expectException(MediaValidatorMissingException::class);
        $this->expectExceptionMessage((new MediaValidatorMissingException('notExistingType'))->getMessage());

        $file = $this->getUploadFixture('image.png');
        $this->getUploadService()->upload($file, 'test', 'notExistingType', Context::createDefaultContext());
    }

    private function getUploadFixture(string $filename): UploadedFile
    {
        return new UploadedFile(self::FIXTURE_DIR . '/' . $filename, $filename, null, null, true);
    }

    private function getUploadService(): StorefrontMediaUploader
    {
        return new StorefrontMediaUploader(
            $this->getContainer()->get(MediaService::class),
            $this->getContainer()->get(FileSaver::class),
            $this->getContainer()->get(StorefrontMediaValidatorRegistry::class)
        );
    }

    private function removeMedia(string $ids): void
    {
        if (!\is_array($ids)) {
            $ids = [$ids];
        }

        $this->getContainer()->get('media.repository')->delete(
            array_map(static fn (string $id) => ['id' => $id], $ids),
            Context::createDefaultContext()
        );
    }
}
