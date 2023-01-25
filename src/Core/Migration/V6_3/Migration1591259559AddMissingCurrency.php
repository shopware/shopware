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
class Migration1591259559AddMissingCurrency extends MigrationStep
{
    private ?string $deLanguage = null;

    private ?string $enLanguage = null;

    public function getCreationTimestamp(): int
    {
        return 1591259559;
    }

    public function update(Connection $connection): void
    {
        if ($this->currencyExists($connection, 'CZK')) {
            return;
        }

        $this->addCurrency($connection, Uuid::randomBytes(), 'CZK', 26.735, 'Kč', 'CZK', 'CZK', 'Tschechische Krone', 'Czech koruna');
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
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
        $languageDefault = $this->getEnLanguageId($connection);
        $languageDE = $this->getDeLanguageId($connection);

        $langId = $connection->fetchOne('
        SELECT `currency`.`id` FROM `currency` WHERE `iso_code` = :code LIMIT 1
        ', ['code' => $isoCode]);

        if (!$langId) {
            $connection->insert('currency', ['id' => $id, 'iso_code' => $isoCode, 'factor' => $factor, 'symbol' => $symbol, 'position' => 1, 'decimal_precision' => 2, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
            if ($languageDefault !== $languageDE) {
                $connection->insert('currency_translation', ['currency_id' => $id, 'language_id' => $languageDefault, 'short_name' => $shortNameEn, 'name' => $nameEn, 'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);
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
        if (!$this->enLanguage) {
            $this->enLanguage = $this->fetchLanguageId('en-GB', $connection);
        }

        return $this->enLanguage;
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

    private function currencyExists(Connection $connection, string $isoCode): bool
    {
        return (bool) $connection->fetchOne('SELECT * FROM currency WHERE LOWER(iso_code) = LOWER(?)', [$isoCode]);
    }
}
