<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
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

class StringFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    private StringFieldSerializer $serializer;

    private StringField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = $this->getContainer()->get(StringFieldSerializer::class);
        $this->field = new StringField('name', 'name');
        $this->field->addFlags(new ApiAware(), new Required());

        $definition = $this->registerDefinition(ProductDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    public function serializerProvider(): array
    {
        return [
            [new KeyValuePair('name', '', true)],
            [new KeyValuePair('name', null, true)],
            [new KeyValuePair('name', ' ', true)],
        ];
    }

    /**
     * @dataProvider serializerProvider
     */
    public function testSerializerNotBlank(KeyValuePair $kvPair): void
    {
        $this->field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));

        /** @var WriteConstraintViolationException|null $exception */
        $exception = null;

        try {
            $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        } catch (\Throwable $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteConstraintViolationException::class, $exception, 'This value should not be blank.');
        static::assertEquals('/name', $exception->getViolations()->get(0)->getPropertyPath());
    }

    public function testSerializerValidatesRequiredField(): void
    {
        $this->field->compile($this->getContainer()->get(DefinitionInstanceRegistry::class));
        $kvPair = new KeyValuePair('name', null, true);

        $this->field->removeFlag(Required::class);

        $encode = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        static::assertNull($encode);

        $this->field->addFlags(new Required());

        static::expectException(WriteConstraintViolationException::class);
        $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
    }
}
