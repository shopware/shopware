<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\AbstractFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\TaxFreeConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * These serializers pre-process the raw payload before `JsonFieldSerializer::encode()` gets to validate it.
 * A scalar payload used to reach that pre-processing and abort the request with a PHP `Error`/`TypeError`
 * instead of a write constraint violation, e.g. on an order import writing `order.price` or
 * `order_line_item.priceDefinition`.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(CalculatedPriceFieldSerializer::class)]
#[CoversClass(CartPriceFieldSerializer::class)]
#[CoversClass(CashRoundingConfigFieldSerializer::class)]
#[CoversClass(PriceDefinitionFieldSerializer::class)]
#[CoversClass(TaxFreeConfigFieldSerializer::class)]
class NonArrayValueRejectionTest extends TestCase
{
    /**
     * @param class-string<AbstractFieldSerializer> $serializerClass
     */
    #[DataProvider('serializerProvider')]
    public function testEncodeRejectsNonArrayValue(string $serializerClass, Field $field, mixed $value): void
    {
        $parameters = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        try {
            iterator_to_array($this->getSerializer($serializerClass)->encode(
                $field,
                new EntityExistence('product', ['someId' => true], true, false, false, []),
                new KeyValuePair('someField', $value, false),
                $parameters
            ), true);

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertCount(1, $exception->getViolations()->findByCodes(Type::INVALID_TYPE_ERROR));
            static::assertSame('/someField', $exception->getViolations()->get(0)->getPropertyPath());
        }
    }

    /**
     * @return iterable<string, array{class-string<AbstractFieldSerializer>, Field, mixed}>
     */
    public static function serializerProvider(): iterable
    {
        $fields = [
            CalculatedPriceFieldSerializer::class => new CalculatedPriceField('some_field', 'someField'),
            CartPriceFieldSerializer::class => new CartPriceField('some_field', 'someField'),
            CashRoundingConfigFieldSerializer::class => new CashRoundingConfigField('some_field', 'someField'),
            TaxFreeConfigFieldSerializer::class => new TaxFreeConfigField('some_field', 'someField'),
            PriceDefinitionFieldSerializer::class => new PriceDefinitionField('some_field', 'someField'),
        ];

        // string offsets and numeric offsets fail differently in PHP, so every serializer sees both
        $values = ['float' => 12.5, 'string' => '2025-10-09', 'int' => 12, 'bool' => true];

        foreach ($fields as $serializerClass => $field) {
            foreach ($values as $valueName => $value) {
                $name = (new \ReflectionClass($serializerClass))->getShortName();

                yield $name . ', ' . $valueName => [$serializerClass, $field, $value];
            }
        }
    }

    /**
     * `CalculatedPriceField` maps `listPrice` and `regulationPrice`, so a scalar there is caught by
     * `JsonFieldSerializer::validateMapping()`, which collects the violation on the write context
     * instead of throwing. Before the guard the scalar fatally hit `unset($value['listPrice']['extensions'])`.
     */
    #[DataProvider('nestedNonArrayValueProvider')]
    public function testEncodeReportsNestedNonArrayValue(string $property, mixed $value): void
    {
        $parameters = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );

        $encoded = iterator_to_array($this->getSerializer(CalculatedPriceFieldSerializer::class)->encode(
            new CalculatedPriceField('some_field', 'someField'),
            new EntityExistence('product', ['someId' => true], true, false, false, []),
            new KeyValuePair('someField', $value, false),
            $parameters
        ), true);

        $errors = iterator_to_array($parameters->getContext()->getExceptions()->getErrors(), false);

        static::assertCount(1, $errors);
        static::assertSame('/0/someField/' . $property, $errors[0]['source']['pointer']);
        static::assertStringNotContainsString($property, (string) $encoded['some_field']);
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function nestedNonArrayValueProvider(): iterable
    {
        $base = ['unitPrice' => 1, 'totalPrice' => 1, 'quantity' => 1, 'calculatedTaxes' => [], 'taxRules' => []];

        yield 'scalar listPrice' => ['listPrice', [...$base, 'listPrice' => 5]];
        yield 'scalar regulationPrice' => ['regulationPrice', [...$base, 'regulationPrice' => 7]];
    }

    /**
     * @param class-string<AbstractFieldSerializer> $serializerClass
     */
    private function getSerializer(string $serializerClass): AbstractFieldSerializer
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $validator,
            static::createStub(EntityWriteGateway::class)
        );

        return $serializerClass === PriceDefinitionFieldSerializer::class
            ? new PriceDefinitionFieldSerializer($registry, $validator, new RuleConditionRegistry([]))
            : new $serializerClass($validator, $registry);
    }
}
