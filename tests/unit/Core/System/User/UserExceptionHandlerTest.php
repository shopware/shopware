<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\User;

use Doctrine\DBAL\Driver\PDO\Exception as PdoException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\User\UserException;
use Shopware\Core\System\User\UserExceptionHandler;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(UserExceptionHandler::class)]
class UserExceptionHandlerTest extends TestCase
{
    #[DataProvider('usernameConstraintErrors')]
    public function testConvertsDuplicateUsername(string $message): void
    {
        $exception = new UniqueConstraintViolationException(new PdoException($message, '23000', 1062), null);

        $result = (new UserExceptionHandler())->matchException($exception);

        static::assertInstanceOf(UserException::class, $result);
        static::assertSame('USER__USERNAME_NOT_UNIQUE', $result->getErrorCode());
        static::assertSame(400, $result->getStatusCode());
        static::assertSame('The username is already in use.', $result->getMessage());
        static::assertSame($exception, $result->getPrevious());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function usernameConstraintErrors(): iterable
    {
        yield 'MariaDB unqualified constraint' => ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'admin\' for key \'uniq.user.username\''];
        yield 'MySQL qualified constraint' => ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'admin\' for key \'user.uniq.user.username\''];
    }

    public function testDoesNotConvertOtherConstraintsOrExceptionTypes(): void
    {
        $handler = new UserExceptionHandler();

        static::assertNull($handler->matchException(new UniqueConstraintViolationException(
            new PdoException('Duplicate entry \'admin@example.com\' for key \'uniq.user.email\'', '23000', 1062),
            null,
        )));
        static::assertNull($handler->matchException(new \RuntimeException('Duplicate entry \'admin\' for key \'uniq.user.username\'')));
    }
}
