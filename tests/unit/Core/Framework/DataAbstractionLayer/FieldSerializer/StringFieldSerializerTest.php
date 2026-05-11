<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowEmptyString;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
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
#[CoversClass(StringFieldSerializer::class)]
#[Group('FieldSerializer')]
#[Group('DAL')]
class StringFieldSerializerTest extends TestCase
{
    private StringFieldSerializer $serializer;

    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    protected function setUp(): void
    {
        $this->definitionInstanceRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $validator = new RecursiveValidator(
            new ExecutionContextFactory($this->createMock(TranslatorInterface::class)),
            new BlackHoleMetadataFactory(),
            new ConstraintValidatorFactory()
        );

        $this->serializer = new StringFieldSerializer(
            $validator,
            $this->definitionInstanceRegistry,
            new HtmlSanitizer(null, false)
        );
    }

    public function testChoiceIsNonStrictByDefault(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b']));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'c', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 'c'], $encoded);
    }

    public function testChoiceStrictAcceptsValidValue(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b'], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'a', true);

        $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));

        static::assertSame(['test' => 'a'], $encoded);
    }

    public function testChoiceStrictRejectsInvalidValue(): void
    {
        $field = (new StringField('test', 'test'))->addFlags(new Choice(['a', 'b'], strict: true));
        $field->compile($this->definitionInstanceRegistry);

        $existence = EntityExistence::createEmpty();
        $kv = new KeyValuePair('test', 'c', true);

        static::expectExceptionObject(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation('Invalid choice.', 'Invalid choice.', [], null, '/test', 'c'),
            ])
        ));

        iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
    }

    /**
     * @param list<Flag> $flags
     */
    #[DataProvider('encodeProvider')]
    public function testEncode(bool|string|null $input, ?string $expected, bool $expectError, array $flags = [], bool $exists = false, ?int $maxLength = null): void
    {
        $field = new StringField('name', 'name', $maxLength ?? 255);
        $field->addFlags(...$flags);

        $existence = new EntityExistence(null, [], $exists, false, false, []);
        $kv = new KeyValuePair('name', $input, true);

        try {
            $encoded = iterator_to_array($this->serializer->encode($field, $existence, $kv, $this->createWriteParameterBag()));
        } catch (WriteConstraintViolationException $exception) {
            static::assertTrue($expectError);
            static::assertSame('/name', $exception->getViolations()->get(0)->getPropertyPath());

            return;
        }

        static::assertFalse($expectError);
        static::assertSame(['name' => $expected], $encoded);
    }

    /**
     * @return array<string, array{bool|string|null, ?string, bool, 3?: list<Flag>, 4?: bool, 5?: int}>
     */
    public static function encodeProvider(): array
    {
        return [
            'create null required' => [null, null, true, [new Required()]],
            'create null optional' => [null, null, false],
            'update null required' => [null, null, true, [new Required()], true],
            'update null optional' => [null, null, false, [], true],
            'create empty required' => ['', null, true, [new Required()]],
            'create empty optional' => ['', null, false],
            'update empty required' => ['', null, true, [new Required()], true],
            'update empty optional' => ['', null, false, [], true],
            'create space required' => [' ', null, true, [new Required()]],
            'create space optional' => [' ', null, false],
            'create space allow empty' => [' ', ' ', false, [new AllowEmptyString()]],
            'update space required' => [' ', null, true, [new Required()], true],
            'update space optional' => [' ', null, false, [], true],
            'update space allow empty' => [' ', ' ', false, [new AllowEmptyString()], true],
            'max length violation' => ['123456789', null, true, [], true, 5],
            'create null allow empty required' => [null, null, true, [new Required(), new AllowEmptyString()]],
            'update null allow empty required' => [null, null, true, [new Required(), new AllowEmptyString()], true],
            'create empty allow empty required' => ['', '', false, [new Required(), new AllowEmptyString()]],
            'update empty allow empty required' => ['', '', false, [new Required(), new AllowEmptyString()], true],
            'required HTML-only content is blank after stripping tags' => ['<test>', null, true, [new Required()]],
            'wrong type throws' => [true, null, true, [new Required()]],
            'string is passed through' => ['test12-B', 'test12-B', false, [new Required()]],
            'HTML is kept when sanitizing is disabled' => ['<test>', '<test>', false, [new Required(), new AllowHtml(false)]],
            'sanitized HTML strips script tag' => ['<script></script>test12-B', 'test12-B', false, [new Required(), new AllowHtml()]],
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
