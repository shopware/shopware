<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Service\FileService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * @internal
 */
#[Package('system-settings')]
class FileServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider fileTypesProvider
     */
    public function testDetectType($fileData): void
    {
        $fileService = new FileService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get('import_export_file.repository')
        );

        $filePath = $fileData['file'];
        $file = fopen($filePath, 'wb');
        static::assertIsResource($file);
        fwrite($file, (string) $fileData['content']);
        fclose($file);

        $uploadedFile = new UploadedFile($filePath, $filePath, $fileData['providedType']);

        $detectedType = $fileService->detectType($uploadedFile);
        static::assertSame($fileData['expectedType'], $detectedType);

        unlink($filePath);
    }

    public function testStoreFile(): void
    {
        /** @var EntityRepository $fileRepository */
        $fileRepository = $this->getContainer()->get('import_export_file.repository');
        $fileService = new FileService(
            $this->getContainer()->get('shopware.filesystem.private'),
            $fileRepository
        );

        $storedFile = $fileService->storeFile(
            Context::createDefaultContext(),
            new \DateTimeImmutable(),
            null,
            'testfile.csv',
            ImportExportLogEntity::ACTIVITY_IMPORT
        );

        static::assertSame('testfile.csv', $storedFile->getOriginalName());

        $dbFile = $fileRepository->search(new Criteria([$storedFile->getId()]), Context::createDefaultContext())->first();
        static::assertNotNull($dbFile);
        static::assertSame('testfile.csv', $dbFile->getOriginalName());
    }

    public static function fileTypesProvider(): iterable
    {
        yield 'CSV file with correct type' => [
            [
                'file' => 'testfile.csv',
                'content' => 'asdf;jkl;wer;\r\n',
                'providedType' => 'text/csv',
                'expectedType' => 'text/csv',
            ],
        ];
        yield 'CSV file with plain type' => [
            [
                'file' => 'testfile.csv',
                'content' => 'asdf;jkl;wer;\r\n',
                'providedType' => 'text/plain',
                'expectedType' => 'text/csv',
            ],
        ];
        yield 'Txt file with plain type' => [
            [
                'file' => 'testfile.txt',
                'content' => 'some text\r\n',
                'providedType' => 'text/plain',
                'expectedType' => 'text/plain',
            ],
        ];
        yield '' => [
            [
                'file' => 'testfile.json',
                'content' => '{}\r\n',
                'providedType' => 'application/json',
                'expectedType' => 'application/json',
            ],
        ];
    }
}
