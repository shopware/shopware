<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MeasurementSystem\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsField;
use Shopware\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer;
use Shopware\Core\Content\MeasurementSystem\MeasurementUnits;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(MeasurementUnitsFieldSerializer::class)]
class MeasurementUnitsFieldSerializerTest extends TestCase
{
    private MeasurementUnitsFieldSerializer $serializer;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = $this->createMock(ValidatorInterface::class);
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);

        $this->serializer = new MeasurementUnitsFieldSerializer($validator, $definitionRegistry);
        $this->existence = $this->createMock(EntityExistence::class);
        $this->parameters = $this->createMock(WriteParameterBag::class);
    }

    /**
     * @return array<string, array{Field, mixed, string|null}>
     */
    public static function encodeProvider(): array
    {
        return [
            'null value should return default units as JSON' => [
                new MeasurementUnitsField('data', 'data'),
                null,
                Json::encode([
                    'system' => MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM,
                    'units' => [
                        'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
                        'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
                    ],
                ]),
            ],
            'MeasurementUnits object should be converted to JSON' => [
                new MeasurementUnitsField('data', 'data'),
                new MeasurementUnits('imperial', ['length' => 'inch', 'weight' => 'pound']),
                Json::encode([
                    'system' => 'imperial',
                    'units' => ['length' => 'inch', 'weight' => 'pound'],
                ]),
            ],
            'array value should be passed through as JSON' => [
                new MeasurementUnitsField('data', 'data'),
                ['system' => 'custom', 'units' => ['length' => 'cm', 'weight' => 'g']],
                Json::encode(['system' => 'custom', 'units' => ['length' => 'cm', 'weight' => 'g']]),
            ],
            'complex MeasurementUnits with multiple units' => [
                new MeasurementUnitsField('data', 'data'),
                new MeasurementUnits('scientific', [
                    'length' => 'mm',
                    'weight' => 'mg',
                    'temperature' => 'celsius',
                    'pressure' => 'bar',
                ]),
                Json::encode([
                    'system' => 'scientific',
                    'units' => [
                        'length' => 'mm',
                        'weight' => 'mg',
                        'temperature' => 'celsius',
                        'pressure' => 'bar',
                    ],
                ]),
            ],
        ];
    }

    #[DataProvider('encodeProvider')]
    public function testEncode(Field $field, mixed $input, ?string $expected): void
    {
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $kvPair = new KeyValuePair('data', $input, true);
        $actual = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        static::assertSame($expected, $actual);
    }

    /**
     * @return array<string, array{string|null, MeasurementUnits}>
     */
    public static function decodeProvider(): array
    {
        return [
            'null value should return default MeasurementUnits' => [
                null,
                MeasurementUnits::createDefaultUnits(),
            ],
            'valid JSON should return MeasurementUnits object' => [
                Json::encode([
                    'system' => 'imperial',
                    'units' => ['length' => 'inch', 'weight' => 'pound'],
                ]),
                new MeasurementUnits('imperial', ['length' => 'inch', 'weight' => 'pound']),
            ],
            'incomplete JSON should return MeasurementUnits with defaults' => [
                Json::encode(['system' => 'custom']),
                new MeasurementUnits('custom', [
                    'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
                    'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
                ]),
            ],
            'JSON without system should use default system' => [
                Json::encode(['units' => ['length' => 'cm', 'weight' => 'g']]),
                new MeasurementUnits(MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM, ['length' => 'cm', 'weight' => 'g']),
            ],
            'empty JSON object should return defaults' => [
                Json::encode([]),
                new MeasurementUnits(MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM, [
                    'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
                    'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
                ]),
            ],
            'complex units should be preserved' => [
                Json::encode([
                    'system' => 'scientific',
                    'units' => [
                        'length' => 'μm',
                        'weight' => 'ng',
                        'temperature' => '°K',
                        'pressure' => 'Pa',
                    ],
                ]),
                new MeasurementUnits('scientific', [
                    'length' => 'μm',
                    'weight' => 'ng',
                    'temperature' => '°K',
                    'pressure' => 'Pa',
                ]),
            ],
        ];
    }

    #[DataProvider('decodeProvider')]
    public function testDecode(?string $input, MeasurementUnits $expected): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));
        $actual = $this->serializer->decode($field, $input);

        static::assertEquals($expected, $actual);
        static::assertSame($expected->getSystem(), $actual->getSystem());
        static::assertSame($expected->getUnits(), $actual->getUnits());
    }

    public function testDecodeWithInvalidJsonReturnsDefault(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $actual = $this->serializer->decode($field, 'invalid json');

        $expected = MeasurementUnits::createDefaultUnits();
        static::assertEquals($expected, $actual);
    }

    public function testDecodeWithNonArrayValueReturnsDefault(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $actual = $this->serializer->decode($field, Json::encode('string value'));

        $expected = MeasurementUnits::createDefaultUnits();
        static::assertEquals($expected, $actual);
    }

    public function testEncodeWithInvalidFieldTypeCallsParentWhichValidates(): void
    {
        $invalidField = new JsonField('data', 'data');
        $kvPair = new KeyValuePair('data', null, true);

        $invalidField->compile($this->createMock(DefinitionInstanceRegistry::class));

        $result = $this->serializer->encode($invalidField, $this->existence, $kvPair, $this->parameters)->current();

        $expected = Json::encode([
            'system' => MeasurementUnits::DEFAULT_MEASUREMENT_SYSTEM,
            'units' => [
                'length' => MeasurementUnits::DEFAULT_LENGTH_UNIT,
                'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT,
            ],
        ]);

        static::assertSame($expected, $result);
    }

    public function testEncodePreservesNullWhenArrayIsProvided(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $arrayData = [
            'system' => 'metric',
            'units' => ['length' => 'cm', 'weight' => 'kg'],
        ];

        $kvPair = new KeyValuePair('data', $arrayData, true);
        $actual = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        $expected = Json::encode($arrayData);
        static::assertSame($expected, $actual);
    }

    public function testEncodeWithComplexMeasurementUnitsObject(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $measurementUnits = new MeasurementUnits('test_system', [
            'length' => 'test_length',
            'weight' => 'test_weight',
            'volume' => 'test_volume',
        ]);

        $kvPair = new KeyValuePair('data', $measurementUnits, true);
        $actual = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        $expected = Json::encode([
            'system' => 'test_system',
            'units' => [
                'length' => 'test_length',
                'weight' => 'test_weight',
                'volume' => 'test_volume',
            ],
        ]);

        static::assertSame($expected, $actual);
    }

    public function testEncodeThenDecodePreservesData(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $originalData = new MeasurementUnits('imperial', [
            'length' => 'ft',
            'weight' => 'lb',
            'temperature' => 'fahrenheit',
        ]);

        $kvPair = new KeyValuePair('data', $originalData, true);
        $encoded = $this->serializer->encode($field, $this->existence, $kvPair, $this->parameters)->current();

        $decoded = $this->serializer->decode($field, $encoded);

        static::assertEquals($originalData, $decoded);
        static::assertSame($originalData->getSystem(), $decoded->getSystem());
        static::assertSame($originalData->getUnits(), $decoded->getUnits());
    }

    public function testDecodeUnitsMergeDefaults(): void
    {
        $field = new MeasurementUnitsField('data', 'data');
        $field->compile($this->createMock(DefinitionInstanceRegistry::class));

        $partialJson = Json::encode([
            'system' => 'custom',
            'units' => ['length' => 'cm'],
        ]);

        $actual = $this->serializer->decode($field, $partialJson);

        $expected = new MeasurementUnits('custom', ['length' => 'cm', 'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT]);

        static::assertEquals($expected, $actual);
        static::assertSame('custom', $actual->getSystem());
        static::assertSame(['length' => 'cm', 'weight' => MeasurementUnits::DEFAULT_WEIGHT_UNIT], $actual->getUnits());
    }
}
