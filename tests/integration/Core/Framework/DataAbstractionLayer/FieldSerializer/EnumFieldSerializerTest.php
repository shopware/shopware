<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\EnumerationField\TestIntegerEnumeration;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field\EnumerationField\TestStringEnumeration;

/**
 * @internald.grothaus@shopwa
 */
#[CoversClass(EnumFieldSerializer::class)]
#[Package('core')]
#[Group('FieldSerializer')]
#[Group('DAL')]
class EnumFieldSerializerTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    public static function serializerProvider(): \Generator
    {
        $update = new EntityExistence('product', [], true, false, false, []);
        $create = new EntityExistence('product', [], false, false, false, []);

        $requiredString = (new EnumField('name', 'name', TestStringEnumeration::Regular))->addFlags(new Required());
        $optionalString = new EnumField('name', 'name', TestStringEnumeration::Regular);

        $requiredInt = (new EnumField('name', 'name', TestIntegerEnumeration::One))->addFlags(new Required());
        $optionalInt = new EnumField('name', 'name', TestIntegerEnumeration::One);

        yield 'Create string with null and required' => [$requiredString, null, null, true, $create];
        yield 'Create string with null and optional' => [$optionalString, null, null, false, $create];
        yield 'Update string with null and required' => [$requiredString, null, null, true, $update];
        yield 'Update string with null and optional' => [$optionalString, null, null, false, $update];
        yield 'Create string with empty and required' => [$requiredString, '', null, true, $create];
        yield 'Create string with empty and optional' => [$optionalString, '', null, false, $create];
        yield 'Update string with empty and required' => [$requiredString, '', null, true, $update];
        yield 'Update string with empty and optional' => [$optionalString, '', null, false, $update];
        yield 'Create string with space and required' => [$requiredString, ' ', null, true, $create];
        yield 'Create string with space and optional' => [$optionalString, ' ', null, false, $create];
        yield 'Update string with space and required' => [$requiredString, ' ', null, true, $update];
        yield 'Update string with space and optional' => [$optionalString, ' ', null, false, $update];

        yield 'Create int with null and required' => [$requiredInt, null, null, true, $create];
        yield 'Create int with null and optional' => [$optionalInt, null, null, false, $create];
        yield 'Update int with null and required' => [$requiredInt, null, null, true, $update];
        yield 'Update int with null and optional' => [$optionalInt, null, null, false, $update];
        yield 'Create int with string and optional' => [$optionalInt, '', null, true, $create];
        yield 'Create int with false and required' => [$optionalInt, false, null, true, $create];
        yield 'Create int from 0 and required' => [$requiredInt, 0, TestIntegerEnumeration::Zero->value, false, $create];
        yield 'Create int from 1 null and optional' => [$optionalInt, 1, TestIntegerEnumeration::One->value, false, $create];
    }

    #[DataProvider('serializerProvider')]
    public function testSerialize(EnumField $field, string|int|bool|null $value, string|int|null $expected, bool $expectError, EntityExistence $existence): void
    {
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $actual = null;
        $exception = null;

        try {
            $kv = new KeyValuePair($field->getPropertyName(), $value, true);

            $params = new WriteParameterBag($this->getContainer()->get(ProductDefinition::class), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

            $actual = $this->getContainer()->get(EnumFieldSerializer::class)
                ->encode($field, $existence, $kv, $params)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        // error cases
        if ($expectError) {
            static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'This value should not be blank.');
            static::assertEquals('/' . $field->getPropertyName(), $exception->getViolations()->get(0)->getPropertyPath());

            return;
        }

        static::assertNull($exception);
        static::assertEquals($expected, $actual);
    }
}
