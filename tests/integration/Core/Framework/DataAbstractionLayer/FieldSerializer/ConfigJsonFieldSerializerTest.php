<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\JsonDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class ConfigJsonFieldSerializerTest extends TestCase
{
    use CacheTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use KernelTestBehaviour;

    private ConfigJsonFieldSerializer $serializer;

    private ConfigJsonField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $this->serializer = static::getContainer()->get(ConfigJsonFieldSerializer::class);
        $this->field = new ConfigJsonField('data', 'data');
        $this->field->addFlags(new ApiAware(), new Required());

        $definition = $this->registerDefinition(JsonDefinition::class);
        $this->existence = new EntityExistence($definition->getEntityName(), [], false, false, false, []);

        $this->parameters = new WriteParameterBag(
            $definition,
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }

    /**
     * @return iterable<string, list<string|int|float|false|array<string, mixed>|list<int>|null>>
     */
    public static function serializerProvider(): iterable
    {
        yield 'serializer string' => ['string'];
        yield 'serializer 11234' => [11234];
        yield 'serializer 11234 point 123243' => [11234.123243];
        yield 'serializer foo sadfsadf bar a 1234' => [['foo' => 'sadfsadf', 'bar' => ['a' => 1234]]];
        yield 'serializer 1 2 3' => [[1, 2, 3]];
        yield 'serializer null' => [null];
        yield 'serializer false' => [false];
        yield 'serializer 0' => [0];
        yield 'serializer scenario 9' => [''];
    }

    /**
     * @param float|false|int|string|array<string, mixed>|list<int>|null $input
     */
    #[DataProvider('serializerProvider')]
    public function testSerializer(array|float|false|int|string|null $input): void
    {
        $kvPair = new KeyValuePair('password', $input, true);
        $encoded = $this->serializer->encode($this->field, $this->existence, $kvPair, $this->parameters)->current();
        $decoded = $this->serializer->decode($this->field, $encoded);

        static::assertSame($input, $decoded, 'Output should be equal to the input');
    }
}
