<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Version\CalculatedPriceFieldTestDefinition;

/**
 * @internal
 */
#[CoversClass(CalculatedPriceFieldSerializer::class)]
class CalculatedPriceFieldSerializerTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    private CalculatedPriceFieldSerializer $serializer;

    private CalculatedPriceField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = static::getContainer()->get(CalculatedPriceFieldSerializer::class);
        $this->field = new CalculatedPriceField('calculatedPrice', 'calculatedPrice');

        $definition = $this->registerDefinition(CalculatedPriceFieldTestDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public function testEncodeStripsExtensionsFromCalculatedPriceListPriceAndRegulationPrice(): void
    {
        $listPriceWithExtensions = ListPrice::createFromUnitPrice(100, 100);
        $listPriceWithExtensions->addArrayExtension('test', ['test' => 'test']);

        $regulationPriceWithExtensions = new RegulationPrice(100);
        $regulationPriceWithExtensions->addArrayExtension('test', ['test' => 'test']);

        $calculatedPrice = new CalculatedPrice(
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]),
            1,
            new ReferencePrice(100, 100, 100, 'reference unit'),
            $listPriceWithExtensions,
            $regulationPriceWithExtensions
        );

        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('calculatedPrice', $calculatedPrice, true),
            $this->parameters
        ));

        $arrayEncoded = \json_decode($encoded['calculatedPrice'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayNotHasKey('extensions', $arrayEncoded);
        static::assertArrayHasKey('listPrice', $arrayEncoded);
        static::assertArrayNotHasKey('extensions', $arrayEncoded['listPrice']);
        static::assertArrayHasKey('regulationPrice', $arrayEncoded);
        static::assertArrayNotHasKey('extensions', $arrayEncoded['regulationPrice']);
    }

    public function testEncodeWithoutListPrice(): void
    {
        $calculatedPrice = new CalculatedPrice(
            50,
            50,
            new CalculatedTaxCollection(),
            new TaxRuleCollection([new TaxRule(19, 100)]),
            1,
            null,
            null,
            new RegulationPrice(50)
        );

        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('calculatedPrice', $calculatedPrice, true),
            $this->parameters
        ));

        $arrayEncoded = \json_decode($encoded['calculatedPrice'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($arrayEncoded['listPrice'] ?? null);
        static::assertArrayHasKey('regulationPrice', $arrayEncoded);
    }

    public function testEncodeWithoutRegulationPrice(): void
    {
        $calculatedPrice = new CalculatedPrice(
            50,
            50,
            new CalculatedTaxCollection(),
            new TaxRuleCollection([new TaxRule(19, 100)]),
            1,
            null,
            ListPrice::createFromUnitPrice(50, 50),
            null
        );

        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('calculatedPrice', $calculatedPrice, true),
            $this->parameters
        ));

        $arrayEncoded = \json_decode($encoded['calculatedPrice'], true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('listPrice', $arrayEncoded);
        static::assertNull($arrayEncoded['regulationPrice'] ?? null);
    }

    public function testDecodeRoundtrip(): void
    {
        $calculatedPrice = new CalculatedPrice(
            100,
            100,
            new CalculatedTaxCollection(),
            new TaxRuleCollection([new TaxRule(19, 50), new TaxRule(7, 50)]),
            1,
            new ReferencePrice(100, 100, 100, 'reference unit'),
            ListPrice::createFromUnitPrice(100, 100),
            new RegulationPrice(100)
        );

        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('calculatedPrice', $calculatedPrice, true),
            $this->parameters
        ));

        $decoded = $this->serializer->decode($this->field, $encoded['calculatedPrice']);

        $calculatedPriceArray = json_decode(json_encode($calculatedPrice, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $decodedArray = json_decode(json_encode($decoded, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($calculatedPriceArray, $decodedArray);
    }

    public function testDecodeReturnsNullForNull(): void
    {
        static::assertNull($this->serializer->decode($this->field, null));
    }

    public function testDecodeReturnsNullForNonArray(): void
    {
        static::assertNull($this->serializer->decode($this->field, '123'));
    }

    public function testDecodeWithMinimalDataWithoutReferencePriceListPriceAndRegulationPrice(): void
    {
        $minimalJson = json_encode([
            'unitPrice' => 10.0,
            'totalPrice' => 10.0,
            'quantity' => 1,
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
            'calculatedTaxes' => [['tax' => 1.6, 'taxRate' => 19.0, 'price' => 10.0]],
        ], \JSON_THROW_ON_ERROR);

        $decoded = $this->serializer->decode($this->field, $minimalJson);

        static::assertInstanceOf(CalculatedPrice::class, $decoded);
        static::assertSame(10.0, $decoded->getUnitPrice());
        static::assertSame(10.0, $decoded->getTotalPrice());
        static::assertNull($decoded->getReferencePrice());
        static::assertNull($decoded->getListPrice());
        static::assertNull($decoded->getRegulationPrice());
    }

    public function testDecodeWithCalculatedTaxLabel(): void
    {
        $jsonWithLabel = json_encode([
            'unitPrice' => 100.0,
            'totalPrice' => 100.0,
            'quantity' => 1,
            'taxRules' => [['taxRate' => 19.0, 'percentage' => 100.0]],
            'calculatedTaxes' => [
                ['tax' => 19.0, 'taxRate' => 19.0, 'price' => 100.0, 'label' => 'VAT 19%'],
            ],
        ], \JSON_THROW_ON_ERROR);

        $decoded = $this->serializer->decode($this->field, $jsonWithLabel);

        static::assertInstanceOf(CalculatedPrice::class, $decoded);
        $calculatedTaxes = $decoded->getCalculatedTaxes();
        static::assertCount(1, $calculatedTaxes);
        $first = $calculatedTaxes->first();
        static::assertInstanceOf(CalculatedTax::class, $first);
        static::assertSame('VAT 19%', $first->getLabel());
    }

    public function testDecodeWithZeroListPrice(): void
    {
        $field = new CalculatedPriceField('price', 'price');

        $data = [
            'unitPrice' => 100,
            'totalPrice' => 100,
            'quantity' => 1,
            'calculatedTaxes' => [],
            'taxRules' => [],
            'listPrice' => [
                'price' => 0,
                'discount' => 0,
                'percentage' => 0,
            ],
        ];

        $result = $this->serializer->decode($field, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertInstanceOf(CalculatedPrice::class, $result);
        static::assertNull($result->getListPrice());
    }

    public function testDecodeWithValidListPrice(): void
    {
        $field = new CalculatedPriceField('price', 'price');

        $data = [
            'unitPrice' => 100,
            'totalPrice' => 100,
            'quantity' => 1,
            'calculatedTaxes' => [],
            'taxRules' => [],
            'listPrice' => [
                'price' => 200,
                'discount' => -100,
                'percentage' => 50,
            ],
        ];

        $result = $this->serializer->decode($field, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertInstanceOf(CalculatedPrice::class, $result);
        static::assertInstanceOf(ListPrice::class, $result->getListPrice());
        static::assertSame(200.0, $result->getListPrice()->getPrice());
    }

    public function testDecodeWithoutListPrice(): void
    {
        $field = new CalculatedPriceField('price', 'price');

        $data = [
            'unitPrice' => 100,
            'totalPrice' => 100,
            'quantity' => 1,
            'calculatedTaxes' => [],
            'taxRules' => [],
        ];

        $result = $this->serializer->decode($field, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertInstanceOf(CalculatedPrice::class, $result);
        static::assertNull($result->getListPrice());
    }
}
