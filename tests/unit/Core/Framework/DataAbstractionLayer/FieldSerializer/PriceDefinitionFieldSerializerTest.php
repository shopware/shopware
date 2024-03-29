<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(PriceDefinitionFieldSerializer::class)]
class PriceDefinitionFieldSerializerTest extends TestCase
{
    private MockObject&RuleConditionRegistry $ruleConditionRegistry;

    private PriceDefinitionFieldSerializer $fieldSerializer;

    protected function setUp(): void
    {
        $definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->ruleConditionRegistry = $this->createMock(RuleConditionRegistry::class);
        $this->fieldSerializer = new PriceDefinitionFieldSerializer(
            $definitionInstanceRegistry,
            Validation::createValidator(),
            $this->ruleConditionRegistry
        );
    }

    public function testEncodeConstraintViolation(): void
    {
        static::expectException(WriteConstraintViolationException::class);

        $rule = new LineItemListPriceRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ]);

        $this->ruleConditionRegistry->method('getRuleInstance')->willReturn(new LineItemListPriceRule());

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

        $this->ruleConditionRegistry->method('getRuleInstance')->willReturn(new LineItemListPriceRule());
        $this->ruleConditionRegistry->method('has')->willReturn(true);
        $this->ruleConditionRegistry->method('getRuleClass')->willReturn(LineItemListPriceRule::class);

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
}
