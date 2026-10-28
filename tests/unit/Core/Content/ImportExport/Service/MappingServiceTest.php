<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\ImportExport\ImportExportProfileEntity;
use Shopware\Core\Content\ImportExport\Service\AbstractFileService;
use Shopware\Core\Content\ImportExport\Service\MappingService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(MappingService::class)]
class MappingServiceTest extends TestCase
{
    private string $csvPath = '';

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->csvPath !== '' && is_file($this->csvPath)) {
            unlink($this->csvPath);
        }
    }

    /**
     * @param list<array{key: string, mappedKey: string}> $profileMapping
     */
    #[DataProvider('guessKeyProvider')]
    public function testGetMappingFromTemplateGuessesKey(array $profileMapping, string $column, string $expectedKey): void
    {
        $profile = new ImportExportProfileEntity();
        $profile->setId('profile-id');
        $profile->setMapping($profileMapping);

        $profileRepository = static::createStub(EntityRepository::class);
        $profileRepository->method('search')->willReturn(new EntitySearchResult(
            'import_export_profile',
            1,
            new EntityCollection([$profile]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        ));

        $fileService = static::createStub(AbstractFileService::class);
        $fileService->method('detectType')->willReturn('text/csv');

        $registry = new StaticDefinitionInstanceRegistry(
            [TaxDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $service = new MappingService(
            $fileService,
            $profileRepository,
            $registry,
            static::createStub(ClockInterface::class)
        );

        $this->csvPath = (string) tempnam(sys_get_temp_dir(), 'mapping-template');
        file_put_contents($this->csvPath, $column . "\n");

        $mappings = $service->getMappingFromTemplate(
            Context::createDefaultContext(),
            new UploadedFile($this->csvPath, 'template.csv', 'text/csv', null, true),
            TaxDefinition::ENTITY_NAME
        );

        $first = $mappings->first();
        static::assertNotNull($first);
        static::assertSame($expectedKey, $first->getKey());
    }

    /**
     * @return iterable<string, array{profileMapping: list<array{key: string, mappedKey: string}>, column: string, expectedKey: string}>
     */
    public static function guessKeyProvider(): iterable
    {
        yield 'key taken from another profile' => [
            'profileMapping' => [['key' => 'rate', 'mappedKey' => 'tax rate column']],
            'column' => 'tax rate column',
            'expectedKey' => 'rate',
        ];

        yield 'key guessed from the field name' => [
            'profileMapping' => [],
            'column' => 'name',
            'expectedKey' => 'name',
        ];

        yield 'unknown column without a profile entry has no key' => [
            'profileMapping' => [],
            'column' => 'unknown column',
            'expectedKey' => '',
        ];

        yield 'profile entry for another column does not apply' => [
            'profileMapping' => [['key' => 'rate', 'mappedKey' => 'other column']],
            'column' => 'unknown column',
            'expectedKey' => '',
        ];

        yield 'profile entry with an empty key is ignored' => [
            'profileMapping' => [['key' => '', 'mappedKey' => 'name']],
            'column' => 'name',
            'expectedKey' => 'name',
        ];

        yield 'profile entry with key zero is used' => [
            'profileMapping' => [['key' => '0', 'mappedKey' => 'unknown column']],
            'column' => 'unknown column',
            'expectedKey' => '0',
        ];
    }
}
