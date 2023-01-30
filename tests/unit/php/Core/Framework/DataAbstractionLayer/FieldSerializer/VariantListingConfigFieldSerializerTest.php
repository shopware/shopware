<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSerializerFieldException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer
 */
class VariantListingConfigFieldSerializerTest extends TestCase
{
    protected VariantListingConfigFieldSerializer $serializer;

    public function setUp(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->serializer = new VariantListingConfigFieldSerializer($definitionRegistry, $validator);
    }

    public function testSingleMainVariant(): void
    {
        $data = [
            'displayParent' => 1,
            'mainVariantId' => Uuid::randomHex(),
            'configuratorGroupConfig' => [],
        ];

        $result = $this->encode($data);

        static::assertSame($data['displayParent'], $result['display_parent']);
        static::assertSame(Uuid::fromHexToBytes($data['mainVariantId']), $result['main_variant_id']);
        static::assertSame('[]', $result['configurator_group_config']);
    }

    public function testExpandedList(): void
    {
        $data = [
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => [
                'id' => Uuid::randomHex(),
                'representation' => 'box',
                'expressionForListings' => true,
            ],
        ];

        $result = $this->encode($data);

        static::assertNull($result['display_parent']);
        static::assertNull($result['main_variant_id']);
        static::assertSame(json_encode($data['configuratorGroupConfig'], \JSON_THROW_ON_ERROR), $result['configurator_group_config']);
    }

    public function testEncodeThrowExceptionOnWrongField(): void
    {
        $field = new JsonField('variant_listing_config', 'variantListingConfig');
        $existence = new EntityExistence('test', ['someId'], true, false, false, []);
        $keyPair = new KeyValuePair('someId', null, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        try {
            iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag));
            static::fail('encode with incorrect field');
        } catch (\Exception $e) {
            static::assertInstanceOf(InvalidSerializerFieldException::class, $e);
        }
    }

    /**
     * @throws \JsonException
     */
    public function testDecode(): void
    {
        $json = '{"displayParent": true, "mainVariantId": "123", "configuratorGroupConfig": null}';

        $field = new VariantListingConfigField('test', 'test');

        $decoded = $this->serializer->decode($field, $json);

        static::assertInstanceOf(VariantListingConfig::class, $decoded);
        static::assertTrue($decoded->getDisplayParent());
        static::assertSame('123', $decoded->getMainVariantId());
        static::assertNull($decoded->getConfiguratorGroupConfig());
    }

    /**
     * @throws \JsonException
     */
    public function testDecodeNullValue(): void
    {
        $field = new VariantListingConfigField('test', 'test');

        $decoded = $this->serializer->decode($field, null);

        static::assertNull($decoded);
    }

    /**
     * @param array<string, int|string|array<string, bool|string>|null>  $data
     *
     * @throws \JsonException
     *
     * @return array<string, int|string|array<string, bool|string>|null>
     */
    private function encode(array $data): array
    {
        $field = new VariantListingConfigField('variant_listing_config', 'variantListingConfig');
        $existence = new EntityExistence('test', ['someId'], true, false, false, []);
        $keyPair = new KeyValuePair('someId', $data, false);
        $bag = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );

        return iterator_to_array($this->serializer->encode($field, $existence, $keyPair, $bag));
    }
}
