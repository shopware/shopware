<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\V6_4\Migration1661771388FixDefaultCountryStatesTranslationAreMissing;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1661771388FixDefaultCountryStatesTranslationAreMissing
 */
class Migration1661771388FixDefaultCountryStatesTranslationAreMissingTest extends TestCase
{
    use ImportTranslationsTrait;
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1661771388FixDefaultCountryStatesTranslationAreMissing $migration;

    private string $deLanguageId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1661771388FixDefaultCountryStatesTranslationAreMissing();
        $this->deLanguageId = $this->getLanguageIds($this->connection, 'de-DE')[0];
        $this->prepare();
    }

    public function testUpdateWithMissingStateTranslations(): void
    {
        $storageDate = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $stateId = $this->connection->fetchOne('SELECT `id` FROM country_state WHERE `short_code` = :shortCode', [
            'shortCode' => 'GB-GWN',
        ]);

        $this->connection->insert('country_state_translation', [
            'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            'country_state_id' => $stateId,
            'name' => 'Gwynedd',
            'created_at' => $storageDate,
        ]);

        static::assertFalse($this->translationExistInLang($stateId, Defaults::LANGUAGE_SYSTEM));
        static::assertTrue($this->translationExistInLang($stateId, $this->deLanguageId));

        $this->migration->update($this->connection);

        static::assertTrue($this->translationExistInLang($stateId, Defaults::LANGUAGE_SYSTEM));
        static::assertTrue($this->translationExistInLang($stateId, $this->deLanguageId));
    }

    public function testUpdateWithoutMissingStateTranslations(): void
    {
        $storageDate = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $countryId = $this->connection->fetchOne('SELECT `id` FROM country WHERE `iso3` = :iso3', [
            'iso3' => 'USA',
        ]);

        static::assertIsString($countryId);
        $stateId = Uuid::randomBytes();
        $this->connection->insert('country_state', [
            'id' => $stateId,
            'country_id' => $countryId,
            'short_code' => 'GB-NAN',
            'created_at' => $storageDate,
        ]);

        $this->connection->insert('country_state_translation', [
            'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            'country_state_id' => $stateId,
            'name' => 'Not available',
            'created_at' => $storageDate,
        ]);

        static::assertFalse($this->translationExistInLang($stateId, Defaults::LANGUAGE_SYSTEM));
        static::assertTrue($this->translationExistInLang($stateId, $this->deLanguageId));

        $this->migration->update($this->connection);

        static::assertFalse($this->translationExistInLang($stateId, Defaults::LANGUAGE_SYSTEM));
        static::assertTrue($this->translationExistInLang($stateId, $this->deLanguageId));
    }

    private function prepare(): void
    {
        $this->connection->executeStatement('DELETE FROM country_state_translation');
    }

    private function translationExistInLang(string $stateId, string $languageId): bool
    {
        return (bool) $this->connection->fetchOne('SELECT 1 FROM country_state_translation WHERE country_state_id = :stateId AND language_id = :languageId', [
            'stateId' => $stateId,
            'languageId' => Uuid::fromHexToBytes($languageId),
        ]);
    }
}
