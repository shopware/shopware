<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\User\UserDefinition;
use Shopware\Core\System\User\Validation\UserPreWriteValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserPreWriteValidator::class)]
class UserPreWriteValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    private WriteContext $context;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [UserDefinition::class, ProductDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());
    }

    #[DataProvider('invalidUsernames')]
    public function testRejectsSurroundingWhitespaceOnInsertAndUpdate(string $username): void
    {
        $definition = $this->registry->get(UserDefinition::class);
        $primaryKey = ['id' => Uuid::randomBytes()];
        $existence = EntityExistence::createEmpty();
        $commands = [
            new InsertCommand($definition, ['username' => $username], $primaryKey, $existence, '/0'),
            new UpdateCommand($definition, ['username' => $username], $primaryKey, $existence, '/1'),
        ];
        $event = new PreWriteValidationEvent($this->context, $commands);

        (new UserPreWriteValidator())->preValidate($event);

        static::assertCount(2, $event->getExceptions()->getExceptions());
        foreach ($event->getExceptions()->getExceptions() as $index => $exception) {
            static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
            $errors = iterator_to_array($exception->getErrors());
            static::assertSame('USER__USERNAME_WHITESPACE', $errors[0]['code']);
            static::assertSame('/' . $index . '/username', $errors[0]['source']['pointer']);
            static::assertSame('400', $errors[0]['status']);
            static::assertSame($username, $commands[$index]->getPayload()['username']);
        }

        $this->expectExceptionObject($event->getExceptions());
        $event->getExceptions()->tryToThrow();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUsernames(): iterable
    {
        yield 'leading space' => [' admin'];
        yield 'trailing space' => ['admin '];
        yield 'leading tab' => ["\tadmin"];
        yield 'trailing newline' => ["admin\n"];
        yield 'leading non-breaking space' => ["\u{00A0}admin"];
        yield 'trailing ideographic space' => ["admin\u{3000}"];
    }

    public function testAllowsValidUsernamesAndUnrelatedWrites(): void
    {
        $definition = $this->registry->get(UserDefinition::class);
        $primaryKey = ['id' => Uuid::randomBytes()];
        $existence = EntityExistence::createEmpty();
        $event = new PreWriteValidationEvent($this->context, [
            new InsertCommand($definition, ['username' => 'Admin'], $primaryKey, $existence, '/0'),
            new UpdateCommand($definition, ['username' => 'first last'], $primaryKey, $existence, '/1'),
            new UpdateCommand($definition, ['first_name' => 'Changed'], $primaryKey, $existence, '/2'),
            new DeleteCommand($definition, $primaryKey, $existence),
            new InsertCommand($this->registry->get(ProductDefinition::class), ['name' => ' Product '], $primaryKey, $existence, '/3'),
        ]);

        (new UserPreWriteValidator())->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
