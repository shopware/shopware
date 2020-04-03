<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Commands;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Command\ImportEntityCommand;
use Shopware\Core\Content\ImportExport\Exception\FileNotReadableException;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Demodata\DemodataService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Tester\CommandTester;

class ImportEntityCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const DEFAULT_CUSTOMER_IMPORT_PROFILE = 'Default customer';
    private const TEST_IMPORT_FILE_PATH = '/tmp/file.csv';

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
        static::markTestSkipped('fix tests');

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
        $commandTester->setInputs([self::DEFAULT_CUSTOMER_IMPORT_PROFILE]);

        try {
            $commandTester->execute($args);
            static::fail('Non existing file should not be readable.');
        } catch (\Exception $e) {
            static::assertInstanceOf(FileNotReadableException::class, $e);
            static::assertRegExp('/Import file is not readable/', $e->getMessage());
        }
    }

    public function testImportCustomersEmptyInputFile(): void
    {
        $this->prepareCSVImportFile([], '', '');

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CUSTOMER_IMPORT_PROFILE]);

        try {
            $commandTester->execute($args);
            static::fail('Expected exception not thrown.');
        } catch (\Exception $e) {
            static::assertEquals('Invalid CSV file. Missing header', $e->getMessage());
        }
    }

    public function testImportCustomersSuccessful(): void
    {
        $num = 3;
        $profile = $this->getProfileByName(self::DEFAULT_CUSTOMER_IMPORT_PROFILE);

        $data = $this->prepareCustomerImportData($profile, $num);
        $this->prepareCSVImportFile($data, $profile->getDelimiter(), $profile->getEnclosure());

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CUSTOMER_IMPORT_PROFILE]);
        $commandTester->execute($args);

        $message = $commandTester->getDisplay();
        static::assertRegExp(sprintf('/\[OK\] Successfully imported %s records in \d+ seconds/', $num), $message);
    }

    public function testImportCustomersInvalidCSV(): void
    {
        $num = 5;
        $invalidLine = 3;
        $profile = $this->getProfileByName(self::DEFAULT_CUSTOMER_IMPORT_PROFILE);

        $data = $this->prepareCustomerImportData($profile, $num);
        array_shift($data[$invalidLine - 1]);
        $this->prepareCSVImportFile($data, $profile->getDelimiter(), $profile->getEnclosure());

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CUSTOMER_IMPORT_PROFILE]);

        try {
            $commandTester->execute($args);
            static::fail('Expected exception not thrown.');
        } catch (\Exception $e) {
            static::assertEquals(
                sprintf('Invalid CSV file. Number of columns mismatch in line %d', $invalidLine),
                $e->getMessage()
            );
        }
    }

    public function testImportCustomersInvalidDelimiter(): void
    {
        $num = 3;
        $profile = $this->getProfileByName(self::DEFAULT_CUSTOMER_IMPORT_PROFILE);

        $data = $this->prepareCustomerImportData($profile, $num);
        array_shift($data[1]);
        $this->prepareCSVImportFile($data, '#', $profile->getEnclosure());

        $commandTester = new CommandTester($this->importEntityCommand);
        $args = [
            'file' => self::TEST_IMPORT_FILE_PATH,
            'expireDate' => date('d.m.Y'),
        ];
        $commandTester->setInputs([self::DEFAULT_CUSTOMER_IMPORT_PROFILE]);

        try {
            $commandTester->execute($args);
            static::fail('Expected exception not thrown.');
        } catch (WriteException $e) {
            static::assertGreaterThan(0, count($e->getExceptions()));
        }
    }

    protected function prepareCustomerImportData(ImportExportProfileEntity $profile, int $num = 1): array
    {
        $mapping = $this->getMappings($profile);
        $payment = $mapping['defaultPaymentMethodId'];
        $country = array_keys($mapping['defaultBillingAddress.countryId']);
        $salutation = ['male' => 'mr', 'female' => 'mrs'];

        $faker = Factory::create();
        $faker->seed(12345);

        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $gender = $faker->randomElement(['male', 'female']);
            $firstName = $faker->firstName($gender);
            $lastName = $faker->lastName;
            $salut = $salutation[$gender];

            $data[] = [
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $faker->email,
                'customerNumber' => sprintf('ABC-%06d', $i),
                'salesChannelId' => 'default',
                'birthday' => $faker->dateTimeInInterval('-60 years', '-18 years')->format('d.m.Y'),
                'salutationId' => $salut,
                'defaultPaymentMethodId' => $faker->randomElement($payment),
                'groupId' => 'default',
                'guest' => '1',
                'defaultBillingAddress.firstName' => $firstName,
                'defaultBillingAddress.lastName' => $lastName,
                'defaultBillingAddress.salutationId' => $salut,
                'defaultBillingAddress.street' => sprintf('%s %d', $faker->streetName, $faker->randomDigitNotNull),
                'defaultBillingAddress.zipcode' => $faker->randomNumber(5),
                'defaultBillingAddress.city' => $faker->city,
                'defaultBillingAddress.countryId' => $faker->randomElement($country),
                'defaultShippingAddress.firstName' => $firstName,
                'defaultShippingAddress.lastName' => $lastName,
                'defaultShippingAddress.salutationId' => $salut,
                'defaultShippingAddress.street' => sprintf('%s %d', $faker->streetName, $faker->randomDigitNotNull),
                'defaultShippingAddress.zipcode' => $faker->randomNumber(5),
                'defaultShippingAddress.city' => $faker->city,
                'defaultShippingAddress.countryId' => $faker->randomElement($country),
            ];
        }

        return $data;
    }

    protected function getMappings(ImportExportProfileEntity $profile): array
    {
        $mapping = [];
        foreach ($profile->getMapping() as $entry) {
            $mapping[$entry['fileField']] = $entry['valueSubstitutions'];
        }

        return $mapping;
    }

    protected function prepareCSVImportFile(array $data, string $delimiter, string $enclosure): void
    {
        $fp = fopen(self::TEST_IMPORT_FILE_PATH, 'w+');
        if (!empty($data)) {
            // Header line.
            fputcsv($fp, array_keys($data[0]), $delimiter, $enclosure);
            foreach ($data as $line) {
                // Data lines.
                fputcsv($fp, array_values($line), $delimiter, $enclosure);
            }
        }
        fclose($fp);
    }

    protected function getProfileByName(string $name): ImportExportProfileEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->profileRepository->search($criteria, $this->context)->getEntities()->first();
    }
}
