<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Payment\Cleanup;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cleanup\CleanupPaymentTokenTaskHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('checkout')]
class CleanupPaymentTokenHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testHandler(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $now = new \DateTimeImmutable();

        $sql = <<<'SQL'
                INSERT INTO payment_token (token, expires)
                VALUES
                    ('token-1', '%s'),
                    ('token-2', '%s'),
                    ('token-3', '%s')
            SQL;
        $sql = \sprintf(
            $sql,
            $now->modify('-10 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $now->modify('-15 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $now->modify('+30 minutes')->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );

        $connection->executeStatement($sql);

        $handler = static::getContainer()->get(CleanupPaymentTokenTaskHandler::class);
        $handler->run();

        $count = $connection->fetchOne('SELECT count(*) FROM payment_token');

        static::assertSame(1, (int) $count);
    }
}
