<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Context\ExecutionContextFactory;
use Symfony\Component\Validator\Mapping\Factory\BlackHoleMetadataFactory;
use Symfony\Component\Validator\Validator\RecursiveValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CashRoundingConfigFieldSerializer::class)]
class CashRoundingConfigFieldSerializerTest extends TestCase
{
    private CashRoundingConfigFieldSerializer $serializer;

    private CashRoundingConfigField $field;

    private EntityExistence $existence;

    private WriteParameterBag $parameters;

    protected function setUp(): void
    {
        $validator = new RecursiveValidator(
            new ExecutionContextFactory(static::createStub(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new CashRoundingConfigFieldSerializer($validator, new StaticDefinitionInstanceRegistry(
            [new ProductDefinition()],
            $validator,
            static::createStub(EntityWriteGateway::class)
        ));

        $this->field = new CashRoundingConfigField('some_field', 'someField');
        $this->existence = new EntityExistence('product', ['someId' => true], true, false, false, []);
        $this->parameters = new WriteParameterBag(
            new ProductDefinition(),
            WriteContext::createFromContext(Context::createDefaultContext()),
            '/0',
            new WriteCommandQueue()
        );
    }

    public function testEncodeStripsExtensions(): void
    {
        $encoded = iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('someField', [
                'decimals' => 2,
                'interval' => 0.01,
                'roundForNet' => true,
                'extensions' => ['test' => ['test' => 'test']],
            ], true),
            $this->parameters
        ), true);

        // the mapped `BoolField` stores its value as an int
        static::assertSame(
            ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => 1],
            json_decode((string) $encoded['some_field'], true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * The serializer pre-processes the payload before `JsonFieldSerializer::encode()` validates it. A scalar
     * used to reach that pre-processing and abort the request with a PHP `Error` instead of a violation.
     */
    #[DataProvider('nonArrayValueProvider')]
    public function testEncodeRejectsNonArrayValue(mixed $value): void
    {
        $this->expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('This value should be of type array.', 'This value should be of type {{ type }}.', [], null, '/someField', $value),
            ])
        ));

        iterator_to_array($this->serializer->encode(
            $this->field,
            $this->existence,
            new KeyValuePair('someField', $value, false),
            $this->parameters
        ), true);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonArrayValueProvider(): iterable
    {
        yield 'number, where PHP reads the offset as an array index' => [12.5];
        yield 'string, where PHP reads the offset as a string offset' => ['2025-10-09'];
    }
}
