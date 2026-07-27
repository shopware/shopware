<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 *
 * @see \Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer\NonArrayValueRejectionTest for the non-array payloads
 */
#[Package('framework')]
#[CoversClass(CartPriceFieldSerializer::class)]
class CartPriceFieldSerializerTest extends TestCase
{
    public function testEncodeStripsExtensionsFromCartPrice(): void
    {
        $cartPrice = new CartPrice(
            10.0,
            11.9,
            10.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_NET
        );
        $cartPrice->addArrayExtension('test', ['test' => 'test']);

        $validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $serializer = new CartPriceFieldSerializer($validator, new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $validator,
            static::createStub(EntityWriteGateway::class)
        ));

        $encoded = iterator_to_array($serializer->encode(
            new CartPriceField('some_field', 'someField'),
            new EntityExistence('product', ['someId' => true], true, false, false, []),
            new KeyValuePair('someField', $cartPrice, true),
            new WriteParameterBag(
                new ProductDefinition(),
                WriteContext::createFromContext(Context::createDefaultContext()),
                '/0',
                new WriteCommandQueue()
            )
        ), true);

        static::assertSame([
            'netPrice' => 10.0,
            'totalPrice' => 11.9,
            'calculatedTaxes' => [],
            'taxRules' => [],
            'positionPrice' => 10.0,
            'rawTotal' => 11.9,
            'taxStatus' => CartPrice::TAX_STATE_NET,
        ], json_decode((string) $encoded['some_field'], true, 512, \JSON_THROW_ON_ERROR));
    }
}
