<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemCustomFieldRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class PriceDefinitionFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider serializerProvider
     */
    public function testSerializer(PriceDefinitionInterface $definition): void
    {
        $serializer = $this->getContainer()->get(PriceDefinitionFieldSerializer::class);

        $encoded = $serializer->encode(
            new PriceDefinitionField('test', 'test'),
            new EntityExistence('', [], false, false, false, []),
            new KeyValuePair('test', $definition, true),
            new WriteParameterBag($this->getContainer()->get(CurrencyDefinition::class), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue())
        );

        $encoded = iterator_to_array($encoded);

        static::assertArrayHasKey('test', $encoded);
        static::assertIsString($encoded['test']);

        $decoded = $serializer->decode(
            new PriceDefinitionField('test', 'test'),
            $encoded['test']
        );

        static::assertEquals($definition, $decoded);
    }

    public static function serializerProvider()
    {
        $rule = new AndRule([
            new OrRule([
                new CurrencyRule(CurrencyRule::OPERATOR_EQ, [Defaults::CURRENCY]),
            ]),
            new CurrencyRule(CurrencyRule::OPERATOR_EQ, [Defaults::CURRENCY]),
        ]);

        yield 'Test quantity price definition' => [
            new QuantityPriceDefinition(100, new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]), 3),
        ];

        yield 'Test absolute price definition' => [
            new AbsolutePriceDefinition(20, $rule),
        ];

        yield 'Test percentage price definition' => [
            new PercentagePriceDefinition(-20, $rule),
        ];

        yield 'Test currency price definition' => [
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

        yield 'Test percentage price definition with bool type custom field rule' => [
            new PercentagePriceDefinition(-20, $rule),
        ];
    }
}
