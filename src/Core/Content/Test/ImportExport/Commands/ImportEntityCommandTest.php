<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Commands;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Command\ImportEntityCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * @group slow
 */
class ImportEntityCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DEFAULT_CATEGORY_IMPORT_PROFILE = 'Default category';
    private const TEST_IMPORT_FILE_PATH = __DIR__ . '/../fixtures/categories.csv';

    /**
     * @var EntityRepositoryInterface
     */
    private $fileRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $profileRepository;

    /**
     * @var ImportEntityCommand
     */
    private $importEntityCommand;

    /**
     * @var DemodataService
     */
    private $demoDataService;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->profileRepository = $this->getContainer()->get('import_export_profile.repository');
        $this->fileRepository = $this->getContainer()->get('import_export_file.repository');
        $this->importEntityCommand = $this->getContainer()->get(ImportEntityCommand::class);
        $this->demoDataService = $this->getContainer()->get(DemodataService::class);
        $this->context = Context::createDefaultContext();
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
        static::assertRegExp(sprintf('/\[OK\] Successfully imported %s records in \d+ seconds/', $num), $message);

        $firstId = '017de84fb11a4e318fd3231317d7def4';
        $lastId = 'fd98f6a0f00f4b05b40e63da076dfd7d';

        $repository = $this->getContainer()->get('category.repository');
        $result = $repository->searchIds(new Criteria([$firstId, $lastId]), Context::createDefaultContext());

        static::assertCount(2, $result->getIds());
    }

    public function testImportWithProfile(): void
    {
        $num = 67;

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
            'profile' => self::DEFAULT_CATEGORY_IMPORT_PROFILE,
        ];
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertRegExp(sprintf('/\[OK\] Successfully imported %s records in \d+ seconds/', $num), $message);

        $firstId = '017de84fb11a4e318fd3231317d7def4';
        $lastId = 'fd98f6a0f00f4b05b40e63da076dfd7d';

        $repository = $this->getContainer()->get('category.repository');
        $result = $repository->searchIds(new Criteria([$firstId, $lastId]), Context::createDefaultContext());

        static::assertCount(2, $result->getIds());
    }
}
