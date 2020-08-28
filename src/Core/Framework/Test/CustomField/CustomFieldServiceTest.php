<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var CustomFieldService
     */
    private $attributeService;

    public function setUp(): void
    {
        $this->attributeRepository = $this->getContainer()->get('custom_field.repository');
        $this->attributeService = $this->getContainer()->get(CustomFieldService::class);
    }

    public function attributeFieldTestProvider(): array
    {
        return [
            [
                CustomFieldTypes::BOOL, BoolField::class,
                CustomFieldTypes::DATETIME, DateTimeField::class,
                CustomFieldTypes::FLOAT, FloatField::class,
                CustomFieldTypes::HTML, LongTextField::class,
                CustomFieldTypes::INT, IntField::class,
                CustomFieldTypes::JSON, JsonField::class,
                CustomFieldTypes::TEXT, LongTextField::class,
            ],
        ];
    }

    /**
     * @dataProvider attributeFieldTestProvider
     */
    public function testGetCustomFieldField(string $attributeType, string $expectedFieldClass): void
    {
        $attribute = [
            'name' => 'test_attr',
            'type' => $attributeType,
        ];
        $this->attributeRepository->create([$attribute], Context::createDefaultContext());

        static::assertInstanceOf(
            $expectedFieldClass,
            $this->attributeService->getCustomField('test_attr')
        );
    }

    public function testOnlyGetActive(): void
    {
        $id = Uuid::randomHex();
        $this->attributeRepository->upsert([[
            'id' => $id,
            'name' => 'test_attr',
            'active' => false,
            'type' => CustomFieldTypes::TEXT,
        ]], Context::createDefaultContext());

        $actual = $this->attributeService->getCustomField('test_attr');
        static::assertNull($actual);

        $this->attributeRepository->upsert([[
            'id' => $id,
            'active' => true,
        ]], Context::createDefaultContext());
        $actual = $this->attributeService->getCustomField('test_attr');
        static::assertNotNull($actual);
    }

    public function testGetCustomFieldLabels(): void
    {
        $context = Context::createDefaultContext();
        $attribute = [
            [
                'name' => 'custom_field_1',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'EN-Label-1',
                        'de-DE' => 'DE-Label-1',
                    ],
                ],
            ],

            [
                'name' => 'custom_field_2',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'EN-Label-2',
                    ],
                ],
            ],
        ];
        $this->attributeRepository->create($attribute, $context);

        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        $labels = $this->attributeService->getCustomFieldLabels(['custom_field_1', 'custom_field_2'], $context);
        static::assertSame($attribute[0]['config']['label']['de-DE'], $labels['custom_field_1']);
        static::assertSame($attribute[1]['config']['label']['en-GB'], $labels['custom_field_2']);
    }

    public function testGetCustomFieldLabelsWithInvalidCustomFieldNames(): void
    {
        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        $labels = $this->attributeService->getCustomFieldLabels(['custom_field_1', 'custom_field_2'], $context);
        static::assertEmpty($labels);

        $labels = $this->attributeService->getCustomFieldLabels([], $context);
        static::assertEmpty($labels);
    }

    public function testGetCustomFieldLabelsWithoutDefaultLanguageTranslation(): void
    {
        $context = Context::createDefaultContext();
        $attribute = [
            [
                'name' => 'custom_field_1',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'foo' => 'EN-Label-1',
                        'bar' => 'DE-Label-1',
                    ],
                ],
            ],

            [
                'name' => 'custom_field_2',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'foo' => 'EN-Label-2',
                    ],
                ],
            ],
        ];
        $this->attributeRepository->create($attribute, $context);

        $chain = [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM];
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, $chain);

        $labels = $this->attributeService->getCustomFieldLabels(['custom_field_1', 'custom_field_2'], $context);
        static::assertEmpty($labels);
    }
}
