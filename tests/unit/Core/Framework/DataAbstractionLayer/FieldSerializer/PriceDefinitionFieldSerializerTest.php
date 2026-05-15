<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(PriceDefinitionFieldSerializer::class)]
class PriceDefinitionFieldSerializerTest extends TestCase
{
    private PriceDefinitionFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->fieldSerializer = new PriceDefinitionFieldSerializer(
            $definitionInstanceRegistry,
            Validation::createValidator(),
            new RuleConditionRegistry([
                new AndRule(),
                new OrRule(),
                new CurrencyRule(),
                new LineItemCustomFieldRule(),
                new LineItemListPriceRule(),
            ])
        );
    }

    public function testEncodeConstraintViolation(): void
    {
        static::expectException(WriteConstraintViolationException::class);

        $rule = new LineItemListPriceRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ]);

        $definition = new PercentagePriceDefinition(10, $rule);
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        iterator_to_array($this->fieldSerializer->encode(
            new PriceDefinitionField('test', 'test'),
            new EntityExistence('', [], false, false, false, []),
            new KeyValuePair('test', $definition, true),
            new WriteParameterBag($this->createMock(CurrencyDefinition::class), $writeContext, '', new WriteCommandQueue())
        ));
    }

    public function testEncodeDecodeWithEmptyOperatorCondition(): void
    {
        $rule = new LineItemListPriceRule();
        $rule->assign(['operator' => Rule::OPERATOR_EMPTY]);

        $definition = new PercentagePriceDefinition(10, $rule);
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $encoded = iterator_to_array($this->fieldSerializer->encode(
            new PriceDefinitionField('test', 'test'),
            new EntityExistence('', [], false, false, false, []),
            new KeyValuePair('test', $definition, true),
            new WriteParameterBag($this->createMock(CurrencyDefinition::class), $writeContext, '', new WriteCommandQueue())
        ));

        static::assertArrayHasKey('test', $encoded);

        $decoded = $this->fieldSerializer->decode(new PriceDefinitionField('test', 'test'), $encoded['test']);

        static::assertEquals($definition, $decoded);
    }

    #[DataProvider('serializerProvider')]
    public function testEncodeDecodeRoundTrip(PriceDefinitionInterface $definition): void
    {
        $encoded = iterator_to_array($this->fieldSerializer->encode(
            new PriceDefinitionField('test', 'test'),
            new EntityExistence('', [], false, false, false, []),
            new KeyValuePair('test', $definition, true),
            new WriteParameterBag($this->createMock(CurrencyDefinition::class), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue())
        ));

        static::assertArrayHasKey('test', $encoded);
        static::assertIsString($encoded['test']);

        $decoded = $this->fieldSerializer->decode(new PriceDefinitionField('test', 'test'), $encoded['test']);

        static::assertEquals($definition, $decoded);
    }

    public static function serializerProvider(): \Generator
    {
        $rule = new AndRule([
            new OrRule([
                new CurrencyRule(CurrencyRule::OPERATOR_EQ, [Defaults::CURRENCY]),
            ]),
            new CurrencyRule(CurrencyRule::OPERATOR_EQ, [Defaults::CURRENCY]),
        ]);

        yield 'quantity price definition' => [
            new QuantityPriceDefinition(100, new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]), 3),
        ];

        yield 'absolute price definition' => [
            new AbsolutePriceDefinition(20, $rule),
        ];

        yield 'percentage price definition' => [
            new PercentagePriceDefinition(-20, $rule),
        ];

        yield 'currency price definition' => [
            new CurrencyPriceDefinition(new PriceCollection([
                new Price(Defaults::CURRENCY, 100, 200, false),
                new Price(Uuid::randomHex(), 200, 300, true),
            ]), $rule),
        ];

        $customFieldsRule = new LineItemCustomFieldRule(
            LineItemCustomFieldRule::OPERATOR_EQ,
            ['name' => 'foobar', 'type' => CustomFieldTypes::BOOL]
        );
        $customFieldsRule->assign([
            'selectedField' => 'foo',
            'selectedFieldSet' => 'bar',
            'renderedFieldValue' => null,
        ]);

        $rule = new AndRule([
            new OrRule([
                $customFieldsRule,
            ]),
            $customFieldsRule,
        ]);

        yield 'percentage price definition with bool custom field rule' => [
            new PercentagePriceDefinition(-20, $rule),
        ];
    }
}
