<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(ConfigJsonFieldSerializer::class)]
class ConfigJsonFieldSerializerTest extends TestCase
{
    private ConfigJsonFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = Validation::createValidator();
        $jsonSerializer = new JsonFieldSerializer($validator, $this->definitionRegistry);
        $this->serializer = new ConfigJsonFieldSerializer($validator, $this->definitionRegistry);

        $this->definitionRegistry
            ->method('getSerializer')
            ->willReturnCallback(fn (string $class) => match ($class) {
                JsonFieldSerializer::class => $jsonSerializer,
                ConfigJsonFieldSerializer::class => $this->serializer,
                default => throw new \LogicException(\sprintf('Unexpected serializer "%s".', $class)),
            });
    }

    /**
     * @param string|int|float|false|array<string, mixed>|list<int>|null $input
     */
    #[DataProvider('serializerProvider')]
    public function testEncodeWrapsAndDecodeUnwrapsConfigJsonValue(array|float|false|int|string|null $input): void
    {
        $field = new ConfigJsonField('data', 'data');
        $field->compile($this->definitionRegistry);

        $encoded = $this->serializer->encode(
            $field,
            EntityExistence::createEmpty(),
            new KeyValuePair('data', $input, true),
            $this->createWriteParameterBag()
        )->current();

        $decoded = $this->serializer->decode($field, $encoded);

        static::assertSame($input, $decoded);
    }

    /**
     * @return iterable<string, array{string|int|float|false|array<string, mixed>|list<int>|null}>
     */
    public static function serializerProvider(): iterable
    {
        yield 'string config value round-trips' => ['string'];
        yield 'integer config value round-trips' => [11234];
        yield 'float config value round-trips' => [11234.123243];
        yield 'nested associative config value round-trips' => [['foo' => 'sadfsadf', 'bar' => ['a' => 1234]]];
        yield 'list config value round-trips' => [[1, 2, 3]];
        yield 'null config value round-trips' => [null];
        yield 'false config value round-trips' => [false];
        yield 'zero config value round-trips' => [0];
        yield 'empty string config value round-trips' => [''];
    }

    private function createWriteParameterBag(): WriteParameterBag
    {
        return new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '',
            new WriteCommandQueue()
        );
    }
}
