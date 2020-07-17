<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1591259559AddMissingCurrency;

class Migration1591259559AddMissingCurrencyTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testMigrationWillSucceed(): void
    {
        // create connection
        $connection = $this->getContainer()->get(Connection::class);

        $this->deleteCurrency('CZK', $connection);

        static::assertFalse($this->currencyExists('czk', $connection));

        // execute migration
        $migration = new Migration1591259559AddMissingCurrency();
        $migration->update($connection);

        // check if currency exists
        static::assertTrue($this->currencyExists('czk', $connection));
    }

    public function testMigrationWillSucceedWhenCurrencyAlreadyExists(): void
    {
        // create connection
        $connection = $this->getContainer()->get(Connection::class);

        // check if currency already exists
        static::assertTrue($this->currencyExists('czk', $connection));

        // execute migration
        $migration = new Migration1591259559AddMissingCurrency();
        $migration->update($connection);

        // check if currency still exists
        static::assertTrue($this->currencyExists('czk', $connection));
    }

    private function fetchLanguageId(string $code, Connection $connection)
    {
        $langId = $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        return $langId;
    }

    private function deleteCurrency(string $isoCode, Connection $connection): void
    {
        $statement = $connection->prepare('DELETE FROM currency WHERE LOWER(iso_code) = LOWER(?)');
        $statement->execute([$isoCode]);
    }

    private function currencyExists(string $isoCode, Connection $connection): bool
    {
        $statement = $connection->prepare('SELECT * FROM currency WHERE LOWER(iso_code) = LOWER(?)');
        $statement->execute([$isoCode]);

        $currencyExists = $statement->rowCount() === 1;

        return $currencyExists;
    }
}
