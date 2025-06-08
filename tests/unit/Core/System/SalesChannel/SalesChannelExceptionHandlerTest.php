<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\Exception\LanguageOfSalesChannelDomainDeleteException;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\System\SalesChannel\SalesChannelExceptionHandler;

/**
 * @internal
 */
#[CoversClass(SalesChannelExceptionHandler::class)]
class SalesChannelExceptionHandlerTest extends TestCase
{
    /**
     * @param class-string|null $expectedException
     */
    #[DataProvider('exceptionProvider')]
    public function testMatchException(string $message, ?string $expectedException): void
    {
        $exceptionHandler = new SalesChannelExceptionHandler();
        $exception = new \Exception($message);
        $result = $exceptionHandler->matchException($exception);

        if ($expectedException === null) {
            static::assertNull($result);

            return;
        }

        static::assertInstanceOf($expectedException, $result);
    }

    public static function exceptionProvider(): \Generator
    {
        yield 'LanguageOfSalesChannelDomainDeleteException' => [
            'message' => 'SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`shopware`.`sales_channel_domain`, CONSTRAINT `fk.sales_channel_domain.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`))',
            'expectedException' => LanguageOfSalesChannelDomainDeleteException::class,
        ];

        yield 'SalesChannelException::salesChannelDomainInUse' => [
            'message' => 'SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row: a foreign key constraint fails (`shopware`.`product_export`, CONSTRAINT `fk.product_export.sales_channel_domain_id` FOREIGN KEY (`sales_channel_domain_id`) REFERENCES `sales_channel_domain` (`id`))',
            'expectedException' => SalesChannelException::class,
        ];

        yield 'Some other exception message' => [
            'message' => 'Some other exception message',
            'expectedException' => null,
        ];
    }
}
