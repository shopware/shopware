<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Shopware\Core\Checkout\Cart\Price\Struct\RegulationPrice;
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

    public function testEncodeRittou(): void
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

        $encoded = $this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('calculatedPrice', $calculatedPrice, true),
            $this->parameters
        );

        $arrayEncoded = \json_decode($encoded->current(), true);

        // check if the listPrice and regulationPrice extensions are not encoded
        static::assertArrayNotHasKey('extensions', $arrayEncoded);
        static::assertArrayHasKey('listPrice', $arrayEncoded);
        static::assertArrayNotHasKey('extensions', $arrayEncoded['listPrice']);
        static::assertArrayHasKey('regulationPrice', $arrayEncoded);
        static::assertArrayNotHasKey('extensions', $arrayEncoded['regulationPrice']);
    }

    public function testDecodeRittou(): void
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
        // to array to compare the values and ignore the object type, avoid phpstan errors
        $calculatedPriceArray = json_decode(json_encode($calculatedPrice, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        $decodedArray = json_decode(json_encode($decoded, \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($calculatedPriceArray, $decodedArray);
    }
}
