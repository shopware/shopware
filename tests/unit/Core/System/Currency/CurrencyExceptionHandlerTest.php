<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyException;
use Shopware\Core\System\Currency\CurrencyExceptionHandler;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
#[CoversClass(CurrencyExceptionHandler::class)]
class CurrencyExceptionHandlerTest extends TestCase
{
    #[DataProvider('duplicateIsoCodeDatabaseExceptions')]
    public function testConvertsDuplicateIsoCodeDatabaseException(string $message): void
    {
        $exception = new \RuntimeException($message);

        $result = (new CurrencyExceptionHandler())->matchException($exception);

        static::assertInstanceOf(CurrencyException::class, $result);
        static::assertSame(CurrencyException::ISO_CODE_NOT_UNIQUE, $result->getErrorCode());
        static::assertSame('The ISO code "EUR" is already in use.', $result->getMessage());
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function duplicateIsoCodeDatabaseExceptions(): \Generator
    {
        yield 'current MariaDB index name' => ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'EUR\' for key \'uniq.currency.iso_code\''];
        yield 'legacy qualified index name' => ['SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'EUR\' for key \'currency.uniq.currency.iso_code\''];
    }

    public function testIgnoresOtherDatabaseExceptions(): void
    {
        $result = (new CurrencyExceptionHandler())->matchException(new \RuntimeException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry'));

        static::assertNull($result);
    }

    public function testPriority(): void
    {
        static::assertSame(ExceptionHandlerInterface::PRIORITY_DEFAULT, (new CurrencyExceptionHandler())->getPriority());
    }
}
