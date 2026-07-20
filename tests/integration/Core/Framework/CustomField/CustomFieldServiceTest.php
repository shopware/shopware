<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\CustomField;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class CustomFieldServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomFieldCollection>
     */
    private EntityRepository $attributeRepository;

    private CustomFieldService $attributeService;

    protected function setUp(): void
    {
        $this->attributeRepository = static::getContainer()->get('custom_field.repository');
        $this->attributeService = static::getContainer()->get(CustomFieldService::class);
    }

    /**
     * @return iterable<string, array{CustomFieldTypes::*, class-string<Field>}>
     */
    public static function attributeFieldTestProvider(): iterable
    {
        yield 'attribute field test custom field types bool bool field' => [CustomFieldTypes::BOOL, BoolField::class];
        yield 'attribute field test custom field types datetime date time field' => [CustomFieldTypes::DATETIME, DateTimeField::class];
        yield 'attribute field test custom field types float float field' => [CustomFieldTypes::FLOAT, FloatField::class];
        yield 'attribute field test custom field types html long text field' => [CustomFieldTypes::HTML, LongTextField::class];
        yield 'attribute field test custom field types int int field' => [CustomFieldTypes::INT, IntField::class];
        yield 'attribute field test custom field types json json field' => [CustomFieldTypes::JSON, JsonField::class];
        yield 'attribute field test custom field types text long text field' => [CustomFieldTypes::TEXT, LongTextField::class];
    }

    /**
     * @param CustomFieldTypes::* $attributeType
     * @param class-string<Field> $expectedFieldClass
     */
    #[DataProvider('attributeFieldTestProvider')]
    public function testGetCustomFieldField(string $attributeType, string $expectedFieldClass): void
    {
        $attribute = [
            'name' => 'test_attr',
            'type' => $attributeType,
        ];
        $this->attributeRepository->create([$attribute], Context::createDefaultContext());

        static::assertInstanceOf($expectedFieldClass, $this->attributeService->getCustomField('test_attr'));
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
        static::assertInstanceOf(JsonField::class, $actual);

        $this->attributeRepository->upsert([[
            'id' => $id,
            'active' => true,
        ]], Context::createDefaultContext());
        $this->attributeService->reset();

        $actual = $this->attributeService->getCustomField('test_attr');
        static::assertInstanceOf(LongTextField::class, $actual);
    }
}
