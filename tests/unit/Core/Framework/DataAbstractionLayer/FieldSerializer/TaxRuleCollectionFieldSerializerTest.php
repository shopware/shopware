<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderPriceModification\OrderPriceModificationDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxRuleCollectionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxRuleCollectionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TaxRuleCollectionFieldSerializer::class)]
class TaxRuleCollectionFieldSerializerTest extends TestCase
{
    private TaxRuleCollectionFieldSerializer $serializer;

    private TaxRuleCollectionField $field;

    protected function setUp(): void
    {
        $validator = Validation::createValidator();

        $this->serializer = new TaxRuleCollectionFieldSerializer($validator, new StaticDefinitionInstanceRegistry(
            [new OrderPriceModificationDefinition()],
            $validator,
            static::createStub(EntityWriteGateway::class)
        ));

        $this->field = new TaxRuleCollectionField('tax_rules', 'taxRules');
    }

    public function testEncodeNormalizesTaxRuleCollectionToFullPercentage(): void
    {
        $encoded = $this->encode(new TaxRuleCollection([
            new TaxRule(19.0, 60.0),
            new TaxRule(7.0, 40.0),
        ]));

        static::assertSame(
            [['taxRate' => 19.0, 'percentage' => 100.0], ['taxRate' => 7.0, 'percentage' => 100.0]],
            json_decode((string) $encoded, true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testEncodeAcceptsPlainArraysAndDropsForeignKeys(): void
    {
        $encoded = $this->encode([
            'first' => ['taxRate' => '19', 'percentage' => 30, 'extensions' => []],
            'second' => ['taxRate' => 7],
        ]);

        static::assertSame(
            [['taxRate' => 19.0, 'percentage' => 100.0], ['taxRate' => 7.0, 'percentage' => 100.0]],
            json_decode((string) $encoded, true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testEncodeKeepsNull(): void
    {
        static::assertNull($this->encode(null));
    }

    public function testDecodeHydratesTaxRuleCollection(): void
    {
        $decoded = $this->serializer->decode(
            $this->field,
            '[{"taxRate": 19, "percentage": 100}, {"taxRate": 7}]'
        );

        static::assertEquals(new TaxRuleCollection([new TaxRule(19.0), new TaxRule(7.0)]), $decoded);
    }

    public function testDecodeReturnsNullForNull(): void
    {
        static::assertNull($this->serializer->decode($this->field, null));
    }

    public function testDecodeReturnsNullForNonArrayJson(): void
    {
        static::assertNull($this->serializer->decode($this->field, '"19"'));
    }

    public function testEncodedValueDecodesToSameTaxRules(): void
    {
        $taxRules = new TaxRuleCollection([new TaxRule(19.0), new TaxRule(7.0)]);

        static::assertEquals($taxRules, $this->serializer->decode($this->field, $this->encode($taxRules)));
    }

    /**
     * @param TaxRuleCollection|array<mixed>|null $value
     */
    private function encode(TaxRuleCollection|array|null $value): ?string
    {
        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            new EntityExistence(OrderPriceModificationDefinition::ENTITY_NAME, ['id' => 'some-id'], true, false, false, []),
            new KeyValuePair('taxRules', $value, true),
            new WriteParameterBag(
                new OrderPriceModificationDefinition(),
                WriteContext::createFromContext(Context::createDefaultContext()),
                '/0',
                new WriteCommandQueue()
            )
        ), true);

        static::assertArrayHasKey('tax_rules', $encoded);
        static::assertTrue($encoded['tax_rules'] === null || \is_string($encoded['tax_rules']));

        return $encoded['tax_rules'];
    }
}
