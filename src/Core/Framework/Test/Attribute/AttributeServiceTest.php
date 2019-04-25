<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Attribute;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Attribute\AttributeService;
use Shopware\Core\Framework\Attribute\AttributeTypes;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextWithHtmlField;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AttributeServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $attributeRepository;

    /**
     * @var AttributeService
     */
    private $attributeService;

    public function setUp(): void
    {
        $this->attributeRepository = $this->getContainer()->get('attribute.repository');
        $this->attributeService = $this->getContainer()->get(AttributeService::class);
    }

    public function attributeFieldTestProvider(): array
    {
        return [
            [
                AttributeTypes::BOOL, BoolField::class,
                AttributeTypes::DATETIME, DateField::class,
                AttributeTypes::FLOAT, FloatField::class,
                AttributeTypes::HTML, LongTextWithHtmlField::class,
                AttributeTypes::INT, IntField::class,
                AttributeTypes::JSON, JsonField::class,
                AttributeTypes::TEXT, LongTextField::class,
            ],
        ];
    }

    /**
     * @dataProvider attributeFieldTestProvider
     */
    public function testGetAttributeField(string $attributeType, string $expectedFieldClass): void
    {
        $attribute = [
            'name' => 'test_attr',
            'type' => $attributeType,
        ];
        $this->attributeRepository->create([$attribute], Context::createDefaultContext());

        static::assertInstanceOf(
            $expectedFieldClass,
            $this->attributeService->getAttributeField('test_attr')
        );
    }

    public function testOnlyGetActive(): void
    {
        $id = Uuid::randomHex();
        $this->attributeRepository->upsert([[
            'id' => $id,
            'name' => 'test_attr',
            'active' => false,
            'type' => AttributeTypes::TEXT,
        ]], Context::createDefaultContext());

        $actual = $this->attributeService->getAttributeField('test_attr');
        static::assertNull($actual);

        $this->attributeRepository->upsert([[
            'id' => $id,
            'active' => true,
        ]], Context::createDefaultContext());
        $actual = $this->attributeService->getAttributeField('test_attr');
        static::assertNotNull($actual);
    }
}
