<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;

/**
 * @internal
 */
class StringFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public static function serializerProvider(): \Generator
    {
        $update = new EntityExistence('product', [], true, false, false, []);
        $create = new EntityExistence('product', [], false, false, false, []);

        $required = (new StringField('name', 'name'))->addFlags(new Required());
        $maxLength = new StringField('name', 'name', 5);
        $optional = new StringField('name', 'name');
        $allowEmpty = (new StringField('name', 'name'))->addFlags(new AllowEmptyString());

        yield 'Create with null and required' => [$required, null, null, true, $create];
        yield 'Create with null and optional' => [$optional, null, null, false, $create];
        yield 'Update with null and required' => [$required, null, null, true, $update];
        yield 'Update with null and optional' => [$optional, null, null, false, $update];

        yield 'Create with empty and required' => [$required, '', null, true, $create];
        yield 'Create with empty and optional' => [$optional, '', null, false, $create];
        yield 'Update with empty and required' => [$required, '', null, true, $update];
        yield 'Update with empty and optional' => [$optional, '', null, false, $update];

        yield 'Create with space and required' => [$required, ' ', null, true, $create];
        yield 'Create with space and optional' => [$optional, ' ', null, false, $create];
        yield 'Create with space and allow empty' => [$allowEmpty, ' ', ' ', false, $create];
        yield 'Update with space and required' => [$required, ' ', null, true, $update];
        yield 'Update with space and optional' => [$optional, ' ', null, false, $update];
        yield 'Update with space and allow empty' => [$allowEmpty, ' ', ' ', false, $update];

        yield 'Test max length' => [$maxLength, '123456789', '12345', true, $update];
    }

    /**
     * @dataProvider serializerProvider
     */
    public function testSerialize(StringField $field, ?string $value, ?string $expected, bool $expectError, EntityExistence $existence): void
    {
        $field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        $actual = null;
        $exception = null;

        try {
            $kv = new KeyValuePair($field->getPropertyName(), $value, true);

            $params = new WriteParameterBag($this->getContainer()->get(ProductDefinition::class), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

            $actual = $this->getContainer()->get(StringFieldSerializer::class)
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
