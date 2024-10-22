<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\TranslationsSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Mapping\MappingCollection;
use Shopware\Core\Content\ImportExport\Processing\Mapping\UpdateByCollection;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\DependencyInjection\Container;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(TranslationsSerializer::class)]
class TranslationSerializerTest extends TestCase
{
    public function testSerializationWithNullTranslations(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $config = $this->getConfig();

        $translations = \iterator_to_array($translationsSerializer->serialize($config, $this->getTranslationsAssociationField(), null));

        static::assertEmpty($translations);
    }

    public function testSerializationWithInvalidField(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $field = new BlobField('foo', 'bar');

        if (!Feature::isActive('v6.7.0.0')) {
            static::expectException(\InvalidArgumentException::class);
            static::expectExceptionMessage('Expected "associationField" to be an instance of "' . \InvalidArgumentException::class . '".');

            $translations = \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $field, []));

            static::assertEmpty($translations);

            return;
        }

        static::expectException(ImportExportException::class);
        static::expectExceptionMessage('Expected "associationField" to be an instance of "Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField".');

        $translations = \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $field, []));

        static::assertEmpty($translations);
    }

    public function testSerialization(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([
            new EntitySearchResult(
                'language',
                1,
                new LanguageCollection([
                    (new LanguageEntity())->assign([
                        'id' => Defaults::LANGUAGE_SYSTEM,
                        'translationCode' => (new LocaleEntity())->assign([
                            'code' => 'en-GB',
                        ]),
                    ]),
                ]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
        ]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = [
            Defaults::LANGUAGE_SYSTEM => [
                'name' => 'foo',
            ],
            'de-DE' => [
                'name' => 'bar',
            ],
        ];

        $translationsSerialized = \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $this->getTranslationsAssociationField(), $translations));

        static::assertSame([
            'translations' => [
                'en-GB' => [
                    'name' => 'foo',
                ],
                'DEFAULT' => [
                    'name' => 'foo',
                ],
                'de-DE' => [
                    'name' => 'bar',
                ],
            ],
        ], $translationsSerialized);
    }

    public function testDeserializationWithEmptyTranslations(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = $translationsSerializer->deserialize($this->getConfig(), $this->getTranslationsAssociationField(), []);

        static::assertNull($translations);
    }

    public function testDeserializationWithInvalidField(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $field = new BlobField('foo', 'bar');

        if (!Feature::isActive('v6.7.0.0')) {
            static::expectException(\InvalidArgumentException::class);
            static::expectExceptionMessage('Expected "associationField" to be an instance of "*ToOneField".');

            $translations = \iterator_to_array($translationsSerializer->serialize($this->getConfig(), $field, []));

            static::assertEmpty($translations);

            return;
        }

        static::expectException(ImportExportException::class);
        static::expectExceptionMessage('Expected "associationField" to be an instance of "*ToOneField".');

        $translations = $translationsSerializer->deserialize($this->getConfig(), $field, []);

        static::assertEmpty($translations);
    }

    public function testDeserialization(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);

        $translationsSerializer = $this->getTranslationSerializer($languageRepository);

        $translations = [
            'DEFAULT' => [
                'name' => 'foo',
            ],
            'de-DE' => [
                'name' => 'bar',
            ],
            'en-GB' => [],
        ];

        $translationsSerialized = $translationsSerializer->deserialize($this->getConfig(), $this->getTranslationsAssociationField(), $translations);

        static::assertSame([
            'de-DE' => [
                'name' => 'bar',
            ],
            Defaults::LANGUAGE_SYSTEM => [
                'name' => 'foo',
            ],
        ], $translationsSerialized);
    }

    public function testSupports(): void
    {
        /** @var StaticEntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = new StaticEntityRepository([]);
        $translationsSerializer = new TranslationsSerializer($languageRepository);

        static::assertTrue($translationsSerializer->supports($this->getTranslationsAssociationField()));
    }

    /**
     * @param StaticEntityRepository<LanguageCollection> $languageRepository
     */
    private function getTranslationSerializer(StaticEntityRepository $languageRepository): TranslationsSerializer
    {
        $translationsSerializer = new TranslationsSerializer(
            $languageRepository,
        );

        $entitySerializer = new EntitySerializer();
        $fieldSerializer = new FieldSerializer();

        $serializerRegistry = new SerializerRegistry([$entitySerializer], [$fieldSerializer]);
        $entitySerializer->setRegistry($serializerRegistry);
        $fieldSerializer->setRegistry($serializerRegistry);
        $translationsSerializer->setRegistry($serializerRegistry);

        return $translationsSerializer;
    }

    private function getConfig(): Config
    {
        return new Config(
            new MappingCollection(),
            [],
            new UpdateByCollection()
        );
    }

    private function getTranslationsAssociationField(): TranslationsAssociationField
    {
        $productTranslationDefinition = new ProductTranslationDefinition();
        $productDefinition = new ProductDefinition();

        $container = new Container();
        $container->set(ProductTranslationDefinition::class, $productTranslationDefinition);
        $container->set(ProductDefinition::class, $productDefinition);
        $productTranslationDefinition->compile(new DefinitionInstanceRegistry($container, [], []));

        $translationAssociationField = new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id');
        $translationAssociationField->compile(new DefinitionInstanceRegistry($container, [], []));

        return $translationAssociationField;
    }
}
