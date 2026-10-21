<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(PrimaryKeyResolver::class)]
class PrimaryKeyResolverTest extends TestCase
{
    /**
     * @param array<string, mixed> $record
     */
    #[TestDox('The updated-by path $updateBy resolves the record id or leaves it unset')]
    #[DataProvider('translationPathProvider')]
    public function testResolvePrimaryKeyFromUpdatedByTranslationPath(
        string $updateBy,
        array $record,
        ?string $languageId,
        ?string $expectedId
    ): void {
        $fieldSerializer = static::createStub(AbstractFieldSerializer::class);
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $languageRepository = static::createStub(EntityRepository::class);
        $languageRepository->method('searchIds')->willReturn(self::idSearchResult($languageId));
        $manufacturerRepository = static::createStub(EntityRepository::class);
        $manufacturerRepository->method('searchIds')->willReturn(self::idSearchResult('found-id'));
        $repositories = [
            LanguageDefinition::ENTITY_NAME => $languageRepository,
            ProductManufacturerDefinition::ENTITY_NAME => $manufacturerRepository,
        ];
        $registry->method('getRepository')->willReturnCallback(
            static fn (string $entityName): EntityRepository => $repositories[$entityName]
        );

        $definition = (new StaticDefinitionInstanceRegistry(
            [ProductManufacturerDefinition::class, ProductManufacturerTranslationDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(ProductManufacturerDefinition::ENTITY_NAME);

        $config = new Config([], [], [['entityName' => ProductManufacturerDefinition::ENTITY_NAME, 'mappedKey' => $updateBy]]);

        $resolver = new PrimaryKeyResolver($registry, $fieldSerializer);
        $result = $resolver->resolvePrimaryKeyFromUpdatedBy($config, $definition, $record);

        static::assertIsArray($result);
        static::assertSame($expectedId, $result['id'] ?? null);
    }

    /**
     * @return iterable<string, array{updateBy: string, record: array<string, mixed>, languageId: string|null, expectedId: string|null}>
     */
    public static function translationPathProvider(): iterable
    {
        yield 'default language segment resolves the record' => [
            'updateBy' => 'translations.DEFAULT.name',
            'record' => ['translations' => ['DEFAULT' => ['name' => 'Acme']]],
            'languageId' => null,
            'expectedId' => 'found-id',
        ];

        yield 'locale segment with a known language resolves the record' => [
            'updateBy' => 'translations.en-GB.name',
            'record' => ['translations' => ['en-GB' => ['name' => 'Acme']]],
            'languageId' => 'language-id',
            'expectedId' => 'found-id',
        ];

        yield 'locale segment with an unknown language leaves the record' => [
            'updateBy' => 'translations.en-GB.name',
            'record' => ['translations' => ['en-GB' => ['name' => 'Acme']]],
            'languageId' => null,
            'expectedId' => null,
        ];

        yield 'empty segment leaves the record' => [
            'updateBy' => 'translations..name',
            'record' => ['translations' => ['' => ['name' => 'Acme']]],
            'languageId' => 'language-id',
            'expectedId' => null,
        ];

        yield 'zero segment leaves the record' => [
            'updateBy' => 'translations.0.name',
            'record' => ['translations' => ['0' => ['name' => 'Acme']]],
            'languageId' => 'language-id',
            'expectedId' => null,
        ];

        yield 'missing segment leaves the record' => [
            'updateBy' => 'translations',
            'record' => ['translations' => 'Acme'],
            'languageId' => 'language-id',
            'expectedId' => null,
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $expected
     */
    #[TestDox('An updated-by many-to-many value resolves to the primary key of the matching tag, and is kept when absent, null or empty')]
    #[DataProvider('manyToManyValueProvider')]
    public function testResolveManyToManyValueFromUpdatedBy(array $record, array $expected): void
    {
        $tagRepository = static::createStub(EntityRepository::class);
        $tagRepository->method('searchIds')->willReturn(self::idSearchResult('tag-id'));
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($tagRepository);

        $definition = (new StaticDefinitionInstanceRegistry(
            [MediaDefinition::class, TagDefinition::class, MediaTagDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(MediaDefinition::ENTITY_NAME);

        $config = new Config([], [], [['entityName' => TagDefinition::ENTITY_NAME, 'mappedKey' => 'name']]);

        $resolver = new PrimaryKeyResolver($registry, static::createStub(AbstractFieldSerializer::class));
        $result = $resolver->resolvePrimaryKeyFromUpdatedBy($config, $definition, $record);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, expected: array<string, mixed>}>
     */
    public static function manyToManyValueProvider(): iterable
    {
        yield 'value is resolved' => [
            'record' => ['tags' => 'new'],
            'expected' => ['tags' => 'tag-id'],
        ];

        yield 'zero string value is resolved' => [
            'record' => ['tags' => '0'],
            'expected' => ['tags' => 'tag-id'],
        ];

        yield 'absent value is left alone' => [
            'record' => [],
            'expected' => [],
        ];

        yield 'null value is left alone' => [
            'record' => ['tags' => null],
            'expected' => ['tags' => null],
        ];

        yield 'empty string value is left alone' => [
            'record' => ['tags' => ''],
            'expected' => ['tags' => ''],
        ];
    }

    private static function idSearchResult(?string $id): IdSearchResult
    {
        $data = [];
        foreach ((array) $id as $rowId) {
            $data[$rowId] = ['primaryKey' => $rowId, 'data' => []];
        }

        return new IdSearchResult(
            \count($data),
            $data,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
