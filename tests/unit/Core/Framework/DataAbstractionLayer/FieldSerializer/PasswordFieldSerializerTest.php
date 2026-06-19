<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;
use Shopware\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(PasswordFieldSerializer::class)]
class PasswordFieldSerializerTest extends TestCase
{
    protected PasswordFieldSerializer $serializer;

    /**
     * @var SystemConfigService&MockObject
     */
    protected SystemConfigService $systemConfigService;

    /**
     * @var ValidatorInterface&MockObject
     */
    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        $definitionRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->serializer = new PasswordFieldSerializer($this->validator, $definitionRegistry, $this->systemConfigService);
    }

    public function testEncodeNotPasswordField(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $existence = new EntityExistence('product', [], false, false, false, []);
        $field = new StringField('password', 'password');

        $kv = new KeyValuePair($field->getPropertyName(), null, true);

        $params = new WriteParameterBag(new ProductDefinition(), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

        $this->serializer->encode($field, $existence, $kv, $params)->getReturn();
    }

    public function testEncodeAllowsNullForOptionalField(): void
    {
        $field = new PasswordField('password', 'password');
        $kv = new KeyValuePair($field->getPropertyName(), null, true);
        $params = new WriteParameterBag(new ProductDefinition(), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

        $encoded = iterator_to_array($this->createSerializerWithRealValidator()->encode(
            $field,
            EntityExistence::createEmpty(),
            $kv,
            $params
        ));

        static::assertSame(['password' => null], $encoded);
    }

    #[DataProvider('requiredExistenceProvider')]
    public function testRequiredPasswordReportsNotBlankViolation(bool $exists): void
    {
        $field = (new PasswordField('password', 'password'))->addFlags(new Required());
        $kv = new KeyValuePair($field->getPropertyName(), null, true);
        $params = new WriteParameterBag(new ProductDefinition(), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

        try {
            iterator_to_array($this->createSerializerWithRealValidator()->encode(
                $field,
                new EntityExistence(null, [], $exists, false, false, []),
                $kv,
                $params
            ));

            static::fail(WriteConstraintViolationException::class . ' not thrown.');
        } catch (WriteConstraintViolationException $exception) {
            static::assertCount(1, $exception->getViolations()->findByCodes(NotBlank::IS_BLANK_ERROR));
        }
    }

    /**
     * @param array<int, Constraint> $constraints
     */
    #[DataProvider('encodeProvider')]
    public function testEncode(string $for, int $minPasswordValue, array $constraints, bool $shouldThrowViolationException, ?string $inputPassword): void
    {
        $constraintViolations = new ConstraintViolationList();
        if ($shouldThrowViolationException) {
            $constraintViolations->add(new ConstraintViolation('test', 'test', [], '', '', ''));

            $expectedViolations = new ConstraintViolationList();
            foreach ($constraints as $_) {
                $expectedViolations->add(new ConstraintViolation('test', 'test', [], '', '/password', ''));
            }
            $this->expectExceptionObject(new WriteConstraintViolationException($expectedViolations));
        }

        $existence = new EntityExistence('product', [], false, false, false, []);
        $field = new PasswordField('password', 'password', \PASSWORD_DEFAULT, [], $for);
        $field->addFlags(new Required());

        $kv = new KeyValuePair($field->getPropertyName(), $inputPassword, true);

        $params = new WriteParameterBag(new ProductDefinition(), WriteContext::createFromContext(Context::createDefaultContext()), '', new WriteCommandQueue());

        if (\array_key_exists($for, PasswordFieldSerializer::CONFIG_MIN_LENGTH_FOR)) {
            $this->systemConfigService->expects($this->once())->method('getInt')->willReturn($minPasswordValue);
        } else {
            $this->systemConfigService->expects($this->never())->method('getInt');
        }

        $this->validator
            ->expects($this->exactly(\count($constraints)))->method('validate')
            ->willReturn($constraintViolations);

        $result = $this->serializer->encode($field, $existence, $kv, $params)->current();

        if ($inputPassword) {
            if (password_get_info($inputPassword)['algo'] !== null) {
                static::assertSame($inputPassword, $result);
            } else {
                static::assertTrue(password_verify($inputPassword, $result));
            }
        }
    }

    /**
     * @return iterable<string, array<int|string|array<int, Constraint>|bool|null>>
     */
    public static function encodeProvider(): iterable
    {
        $minPasswordLength = 8;

        $notBlankConstraints = [
            new NotBlank(),
            new Type('string'),
        ];

        $minLengthConstraints = [
            new NotBlank(),
            new Type('string'),
            new Length(min: $minPasswordLength),
        ];

        yield 'with null value without min length required' => [
            PasswordField::FOR_ADMIN,
            0,
            $notBlankConstraints,
            true,
            null,
        ];

        yield 'with null value with min length required' => [
            PasswordField::FOR_ADMIN,
            $minPasswordLength,
            $minLengthConstraints,
            true,
            null,
        ];

        yield 'success without min length config' => [
            'not_exist',
            $minPasswordLength,
            $notBlankConstraints,
            false,
            'over8characters',
        ];

        yield 'success with min length config' => [
            PasswordField::FOR_ADMIN,
            $minPasswordLength,
            $minLengthConstraints,
            false,
            'over8characters',
        ];

        yield 'unsuccess with min length config' => [
            PasswordField::FOR_ADMIN,
            $minPasswordLength,
            $minLengthConstraints,
            true,
            'short',
        ];

        yield 'success with algorithm password' => [
            PasswordField::FOR_ADMIN,
            $minPasswordLength,
            $minLengthConstraints,
            false,
            password_hash('over8characters', \PASSWORD_DEFAULT),
        ];
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function requiredExistenceProvider(): array
    {
        return [
            'insert' => [false],
            'update' => [true],
        ];
    }

    private function createSerializerWithRealValidator(): PasswordFieldSerializer
    {
        return new PasswordFieldSerializer(
            Validation::createValidator(),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->systemConfigService
        );
    }
}
