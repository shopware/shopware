<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1603293043FixCurrencyTypo;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1603293043FixCurrencyTypo
 */
class Migration1603293043FixCurrencyTypoTest extends TestCase
{
    use MigrationTestTrait;

    final public const wrongTranslation = 'Swedish krone';

    final public const correctTranslation = 'Swedish krona';

    private Connection $connection;

    private string $languageIdEnglish;

    private Migration1603293043FixCurrencyTypo $migration;

    //can change to make language "unavailable"
    private string $englishLanguageLocale = 'en-GB';

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1603293043FixCurrencyTypo();
    }

    /**
     * @dataProvider migrationCases
     */
    public function testMigration(bool $englishAvailable, bool $currencyTranslationAvailable, bool $currencyTranslationChanged, bool $updated_atSet): void
    {
        $this->prepare($englishAvailable);

        $this->connection->update('currency_translation', ['name' => 'Swedish krone'], ['short_name' => 'SEK', 'language_id' => $this->languageIdEnglish]);

        if (!$currencyTranslationAvailable) {
            $this->connection->update('currency_translation', ['short_name' => 'SEK_NA'], ['short_name' => 'SEK']);
        }

        if ($currencyTranslationChanged) {
            $this->connection->update('currency_translation', ['name' => 'Swedish currency'], ['short_name' => 'SEK', 'language_id' => $this->languageIdEnglish]);
        }

        if ($updated_atSet) {
            $currentDateTime = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
            $this->connection->update('currency_translation', ['Updated_at' => $currentDateTime], ['short_name' => 'SEK', 'language_id' => $this->languageIdEnglish]);
        }

        //if one these Parameters is different from the defaults the migration should not run
        if (!$englishAvailable || !$currencyTranslationAvailable || $currencyTranslationChanged || $updated_atSet) {
            $dbData = $this->connection->fetchAllAssociative('SELECT * FROM currency_translation ORDER BY currency_id');
            $expectedHash = md5(serialize($dbData));

            $this->migration->update($this->connection);

            $dbData = $this->connection->fetchAllAssociative('SELECT * FROM currency_translation ORDER BY currency_id');
            $actualHash = md5(serialize($dbData));

            static::assertSame($expectedHash, $actualHash);
        } else {
            $this->migration->update($this->connection);
            $swedishCurrencyNameQuery = $this->connection->createQueryBuilder()
                ->select('ct.name')
                ->from('currency_translation', 'ct')
                ->where('ct.short_name = :kronaEnglishShort')
                ->andWhere('ct.language_id = :englishLanguageId')
                ->andWhere('ct.updated_at IS NOT NULL')
                ->setParameters(['englishLanguageId' => $this->languageIdEnglish, 'kronaEnglishShort' => 'SEK']);
            $swedishCurrencyName = $swedishCurrencyNameQuery->executeQuery()->fetchOne();
            static::assertSame('Swedish krona', $swedishCurrencyName);
        }
    }

    /**
     * @return bool[][]
     */
    public function migrationCases(): array
    {
        return [
            [true, true, true, true],
            [true, true, true, false],
            [true, true, false, true],
            [true, true, false, false],
            [true, false, true, true],
            [true, false, true, false],
            [true, false, false, true],
            [true, false, false, false],
            [false, true, true, true],
            [false, true, true, false],
            [false, true, false, true],
            [false, true, false, false],
            [false, false, true, true],
            [false, false, true, false],
            [false, false, false, true],
            [false, false, false, false],
        ];
    }

    protected function prepare(bool $englishAvailable): void
    {
        $this->setEnLanguageAvailability($englishAvailable);

        $englishLanguageId = $this->connection->createQueryBuilder()
            ->select('lang.id')
            ->from('language', 'lang')
            ->innerJoin('lang', 'locale', 'loc', 'lang.translation_code_id = loc.id')
            ->where('loc.code = :englishLocale')
            ->setParameter('englishLocale', $this->englishLanguageLocale)
            ->execute()
            ->fetchOne();
        static::assertNotNull($englishLanguageId, 'Test failed: English language ID not found');
        $this->languageIdEnglish = $englishLanguageId;

        $currencyId = $this->connection->fetchOne('SELECT id FROM currency WHERE `iso_code` = \'SEK\'');
        $this->connection->delete('currency_translation', [
            'currency_id' => $currencyId,
            'language_id' => $this->languageIdEnglish,
        ]);
        $this->connection->insert(
            'currency_translation',
            [
                'name' => 'Swedish krone',
                'short_name' => 'SEK',
                'currency_id' => $currencyId,
                'language_id' => $this->languageIdEnglish,
                'updated_at' => null,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function setEnLanguageAvailability(bool $available): void
    {
        if ($available) {
            $this->connection->update('locale', ['code' => 'en-GB'], ['code' => 'en_NA']);
            $this->englishLanguageLocale = 'en-GB';
        } else {
            $this->connection->update('locale', ['code' => 'en_NA'], ['code' => 'en-GB']);
            $this->englishLanguageLocale = 'en_NA';
        }
    }
}
