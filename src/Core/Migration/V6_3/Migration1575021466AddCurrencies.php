<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1575021466AddCurrencies extends MigrationStep
{
    private ?string $deLanguage = null;

    private ?string $defaultLanguage = null;

    public function getCreationTimestamp(): int
    {
        return 1575021466;
    }

    public function update(Connection $connection): void
    {
        $this->createCurrencyUniqueConstraint($connection);
        $this->createCurrencies($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createCurrencyUniqueConstraint(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `currency` ADD  CONSTRAINT `uniq.currency.iso_code` UNIQUE (`iso_code`)');
    }

    private function createCurrencies(Connection $connection): void
    {
        $this->addCurrency($connection, Uuid::randomBytes(), 'PLN', 4.33, 'zł', 'PLN', 'PLN', 'Złoty', 'Złoty');
        $this->addCurrency($connection, Uuid::randomBytes(), 'CHF', 1.1, 'Fr', 'CHF', 'CHF', 'Schweizer Franken', 'Swiss francs');
        $this->addCurrency($connection, Uuid::randomBytes(), 'SEK', 10.51, 'kr', 'SEK', 'SEK', 'Schwedische Kronen', 'Swedish krone');
        $this->addCurrency($connection, Uuid::randomBytes(), 'DKK', 7.47, 'kr', 'DKK', 'DKK', 'Dänische Kronen', 'Danish krone');
        $this->addCurrency($connection, Uuid::randomBytes(), 'NOK', 0.099, 'nkr', 'NOK', 'NOK', 'Norwegische Kronen', 'Norwegian krone');
    }

    private function addCurrency(
        Connection $connection,
        string $id,
        string $isoCode,
        float $factor,
        string $symbol,
        string $shortNameDe,
        string $shortNameEn,
        string $nameDe,
        string $nameEn
    ): void {
        $languageEN = $this->getEnLanguageId($connection);
        $languageDE = $this->getDeLanguageId($connection);

        $langId = $connection->fetchOne('
        SELECT `currency`.`id` FROM `currency` WHERE `iso_code` = :code LIMIT 1
        ', ['code' => $isoCode]);

        if (!$langId) {
            $connection->insert('currency', ['id' => $id, 'iso_code' => $isoCode, 'factor' => $factor, 'symbol' => $symbol, 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            if ($languageEN !== $languageDE) {
                $connection->insert('currency_translation', ['currency_id' => $id, 'language_id' => $languageEN, 'short_name' => $shortNameEn, 'name' => $nameEn, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            }

            if ($languageDE) {
                $connection->insert('currency_translation', ['currency_id' => $id, 'language_id' => $languageDE, 'short_name' => $shortNameDe, 'name' => $nameDe, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            }
        }
    }

    private function getDeLanguageId(Connection $connection): ?string
    {
        if (!$this->deLanguage) {
            $this->deLanguage = $this->fetchLanguageId('de-DE', $connection);
        }

        return $this->deLanguage;
    }

    private function getEnLanguageId(Connection $connection): ?string
    {
        if (!$this->defaultLanguage) {
            $this->defaultLanguage = $this->fetchLanguageId('en-GB', $connection);
        }

        return $this->defaultLanguage;
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId && $code !== 'en-GB') {
            return null;
        }

        if (!$langId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $langId;
    }
}
