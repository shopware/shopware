<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
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
#[CoversClass(IntFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class IntFieldSerializerTest extends TestCase
{
    private IntFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = new RecursiveValidator(
            new ExecutionContextFactory($this->createMock(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new IntFieldSerializer(
            $validator,
            $this->definitionInstanceRegistry
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2]));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 3, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 3], $encoded);
    }

    public function testChoiceStrictAcceptsValidValue(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 1, true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 1], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new IntField('test', 'test'))->addFlags(new Choice([1, 2], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 3, true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 3),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    #[DataProvider('encodeProvider')]
    public function testEncode(mixed $input, ?int $expected, bool $required, bool $expectError): void
    {
        $field = new IntField('count', 'count');
        if ($required) {
            $field->addFlags(new Required());
        }
        $field->compile($this->definitionInstanceRegistry);

        try {
            $encoded = iterator_to_array($this->serializer->encode(
                $field,
                EntityExistence::createEmpty(),
                new KeyValuePair('count', $input, false),
                $this->createWriteParameterBag()
            ));
        } catch (WriteConstraintViolationException $exception) {
            static::assertTrue($expectError);
            static::assertSame('/count', $exception->getViolations()->get(0)->getPropertyPath());

            return;
        }

        static::assertFalse($expectError);
        static::assertSame(['count' => $expected], $encoded);
    }

    /**
     * @return array<string, array{mixed, ?int, bool, bool}>
     */
    public static function encodeProvider(): array
    {
        return [
            'required null throws' => [null, null, true, true],
            'wrong type throws' => ['foo', null, true, true],
            'zero is valid' => [0, 0, true, false],
            'integer is valid' => [15, 15, true, false],
            'optional null is valid' => [null, null, false, false],
        ];
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
