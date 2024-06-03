<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Command;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Command\ImportEntityCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @internal
 */
#[Package('services-settings')]
#[Group('slow')]
class ImportEntityCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DEFAULT_CATEGORY_IMPORT_PROFILE = 'Default category';
    private const DEFAULT_CATEGORY_IMPORT_PROFILE_TECHNICAL_NAME = 'default_category';
    private const DEFAULT_PRODUCT_IMPORT_PROFILE_TECHNICAL_NAME = 'default_product';
    private const TEST_IMPORT_FILE_PATH = __DIR__ . '/../fixtures/categories.csv';
    private const TEST_INVALID_IMPORT_FILE_PATH = __DIR__ . '/../fixtures/products_with_invalid.csv';

    private ImportEntityCommand $importEntityCommand;

    protected function setUp(): void
    {
        $this->importEntityCommand = $this->getContainer()->get(ImportEntityCommand::class);
    }

    public function testImportCustomersNoInputFile(): void
    {
        $commandTester = new CommandTester($this->importEntityCommand);
        $noFile = Uuid::randomHex();
        $args = [
            'file' => $noFile,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CATEGORY_IMPORT_PROFILE]);

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('The file "' . $noFile . '" does not exist');
        $commandTester->execute($args);
    }

    public function testImportCategories(): void
    {
        $num = 67;

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CATEGORY_IMPORT_PROFILE]);
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully imported %d records in \d+ seconds/', $num), $message);

        $firstId = '017de84fb11a4e318fd3231317d7def4';
        $lastId = 'fd98f6a0f00f4b05b40e63da076dfd7d';

        $repository = $this->getContainer()->get('category.repository');
        $result = $repository->searchIds(new Criteria([$firstId, $lastId]), Context::createDefaultContext());

        static::assertCount(2, $result->getIds());
    }

    public function testImportWithProfile(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $num = 67;

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
            'profile' => self::DEFAULT_CATEGORY_IMPORT_PROFILE,
        ];
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully imported %d records in \d+ seconds/', $num), $message);

        $firstId = '017de84fb11a4e318fd3231317d7def4';
        $lastId = 'fd98f6a0f00f4b05b40e63da076dfd7d';

        $repository = $this->getContainer()->get('category.repository');
        $result = $repository->searchIds(new Criteria([$firstId, $lastId]), Context::createDefaultContext());

        static::assertCount(2, $result->getIds());
    }

    public function testImportWithProfileTechnicalName(): void
    {
        $num = 67;

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
            '--profile-technical-name' => self::DEFAULT_CATEGORY_IMPORT_PROFILE_TECHNICAL_NAME,
        ];
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully imported %d records in \d+ seconds/', $num), $message);

        $firstId = '017de84fb11a4e318fd3231317d7def4';
        $lastId = 'fd98f6a0f00f4b05b40e63da076dfd7d';

        $repository = $this->getContainer()->get('category.repository');
        $result = $repository->searchIds(new Criteria([$firstId, $lastId]), Context::createDefaultContext());

        static::assertCount(2, $result->getIds());
    }

    public function testImportWithInvalid(): void
    {
        $num = 8;

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_INVALID_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
            '--profile-technical-name' => self::DEFAULT_PRODUCT_IMPORT_PROFILE_TECHNICAL_NAME,
        ];
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertStringContainsString('[WARNING] Not all records could be imported due to errors', $message);
        static::assertMatchesRegularExpression(sprintf('/\[OK\] Successfully imported %d records in \d+ seconds/', $num), $message);

        $repository = $this->getContainer()->get('product.repository');
        $result = $repository->searchIds(new Criteria(), Context::createDefaultContext());

        static::assertCount(8, $result->getIds());
    }

    public function testImportWithInvalidAndRollback(): void
    {
        $num = 8;

        $this->stopTransactionAfter();
        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_INVALID_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
            '--profile-technical-name' => self::DEFAULT_PRODUCT_IMPORT_PROFILE_TECHNICAL_NAME,
            '-r' => true,
            '-p' => true,
        ];
        $commandTester->execute($args);
        $this->startTransactionBefore();

        $message = $commandTester->getDisplay();
        static::assertStringContainsString(sprintf('[ERROR] Errors on import. Rolling back transactions for %d records.', $num), $message);
        static::assertStringContainsString('Integrity constraint violation', $message);

        $repository = $this->getContainer()->get('product.repository');
        $result = $repository->searchIds(new Criteria(), Context::createDefaultContext());

        static::assertCount(0, $result->getIds());
    }
}
