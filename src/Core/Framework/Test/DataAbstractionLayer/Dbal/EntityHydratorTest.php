<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldTestTranslationDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityHydratorTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function testFkExtensionFieldHydration(): void
    {
        $definition = new FkExtensionFieldTest();
        $definition->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();
        $normal = Uuid::randomBytes();
        $extended = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'test',
                'test.normalFk' => $normal,
                'test.extendedFk' => $extended,
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        /** @var ArrayEntity|null $first */
        $first = $structs->first();

        static::assertInstanceOf(ArrayEntity::class, $first);

        static::assertSame('test', $first->get('name'));

        static::assertSame(Uuid::fromBytesToHex($id), $first->get('id'));
        static::assertSame(Uuid::fromBytesToHex($normal), $first->get('normalFk'));

        static::assertTrue($first->hasExtension(EntityReader::FOREIGN_KEYS));
        /** @var ArrayStruct|null $foreignKeys */
        $foreignKeys = $first->getExtension(EntityReader::FOREIGN_KEYS);

        static::assertInstanceOf(ArrayStruct::class, $foreignKeys);

        static::assertTrue($foreignKeys->has('extendedFk'));
        static::assertSame(Uuid::fromBytesToHex($extended), $foreignKeys->get('extendedFk'));
    }

    public function testCustomFieldHydrationWithoutTranslationWithoutInheritance(): void
    {
        $definition = $this->registerDefinition(CustomFieldPlainTestDefinition::class);

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customFields' => '{"custom_test_text": "Example", "custom_test_check": null}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', Context::createDefaultContext());
        static::assertCount(1, $structs);

        $first = $structs->first();
        $customFields = $first->get('customFields');

        static::assertIsArray($customFields);
        static::assertCount(2, $customFields);
        static::assertSame('Example', $customFields['custom_test_text']);
        static::assertNull($customFields['custom_test_check']);
    }

    public function testCustomFieldHydrationWithTranslationWithInheritance(): void
    {
        $definition = $this->registerDefinition(CustomFieldTestDefinition::class, CustomFieldTestTranslationDefinition::class);

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();
        $context = $this->createContext();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": "0"}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": "0"}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": "PARENT DEUTSCH"}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "1"}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        $customFields = $first->get('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": "PARENT DEUTSCH"}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "1"}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        $first = $structs->first();

        $customFields = $first->get('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.parent.translation.customTranslated' => '{"custom_test_text": null}',
                'test.parent.translation.fallback_1.customTranslated' => '{"custom_test_text": "PARENT ENGLISH", "custom_test_check": "0"}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        $first = $structs->first();

        $customFields = $first->get('customTranslated');
        static::assertSame('PARENT ENGLISH', $customFields['custom_test_text']);
        static::assertSame('0', $customFields['custom_test_check']);
    }

    public function testCustomFieldHydrationWithTranslationWithoutInheritance(): void
    {
        $definition = $this->registerDefinition(CustomFieldTestDefinition::class, CustomFieldTestTranslationDefinition::class);

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();
        $context = $this->createContext(false);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.customTranslated' => '{"custom_test_text": null, "custom_test_check": "1"}',
                'test.translation.customTranslated' => '{"custom_test_text": null, "custom_test_check": "1"}',
                'test.translation.fallback_1.customTranslated' => '{"custom_test_text": "Example", "custom_test_check": null}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        $customFields = $first->get('customTranslated');
        static::assertSame('Example', $customFields['custom_test_text']);
        static::assertNull($customFields['custom_test_check']);
    }

    public function testCustomFieldHydrationWithoutTranslationWithInheritance(): void
    {
        $definition = $this->registerDefinition(CustomFieldTestDefinition::class, CustomFieldTestTranslationDefinition::class);

        $hydrator = new EntityHydrator();

        $id = Uuid::randomBytes();
        $context = $this->createContext();

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.custom' => '{"custom_test_text": null, "custom_test_check": null}',
                'test.custom.inherited' => '{"custom_test_text": "PARENT", "custom_test_check": "0"}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        $customFields = $first->get('custom');

        static::assertSame('PARENT', $customFields['custom_test_text']);
        static::assertSame('0', $customFields['custom_test_check']);

        $rows = [
            [
                'test.id' => $id,
                'test.name' => 'example',
                'test.custom' => '{"custom_test_text": null, "custom_test_check": "1"}',
            ],
        ];

        $structs = $hydrator->hydrate(new EntityCollection(), ArrayEntity::class, $definition, $rows, 'test', $context);
        static::assertCount(1, $structs);

        $first = $structs->first();
        $customFields = $first->get('custom');

        static::assertNull($customFields['custom_test_text']);
        static::assertSame('1', $customFields['custom_test_check']);
    }

    private function addLanguage(string $id, ?string $rootLanguage): void
    {
        $translationCodeId = Uuid::randomHex();
        $languageRepository = $this->getContainer()->get('language.repository');
        $languageRepository->create(
            [
                [
                    'id' => $id,
                    'parentId' => $rootLanguage,
                    'name' => $id,
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'translationCode' => [
                        'id' => $translationCodeId,
                        'name' => 'x-' . $translationCodeId,
                        'code' => 'x-' . $translationCodeId,
                        'territory' => $translationCodeId,
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }

    private function createContext(bool $inheritance = true): Context
    {
        $rootLanguageId = Uuid::randomHex();
        $this->addLanguage($rootLanguageId, null);

        return new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [$rootLanguageId, Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            2,
            $inheritance
        );
    }
}

class FkExtensionFieldTest extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'fk_extension_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new StringField('name', 'name'),
            new FkField('normal_fk', 'normalFk', ProductDefinition::class),

            (new FkField('extended_fk', 'extendedFk', ProductDefinition::class))
                ->addFlags(new Extension()),
        ]);
    }
}
