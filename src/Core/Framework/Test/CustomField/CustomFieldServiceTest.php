<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\CustomField;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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

/**
 * @internal
 */
class CustomFieldServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $attributeRepository;

    /**
     * @var CustomFieldService
     */
    private $attributeService;

    protected function setUp(): void
    {
        $this->attributeRepository = $this->getContainer()->get('custom_field.repository');
        $this->attributeService = $this->getContainer()->get(CustomFieldService::class);
    }

    public static function attributeFieldTestProvider(): array
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
}
