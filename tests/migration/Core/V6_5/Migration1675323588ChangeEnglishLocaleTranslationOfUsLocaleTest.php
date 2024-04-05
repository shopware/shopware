<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale::class)]
class Migration1675323588ChangeEnglishLocaleTranslationOfUsLocaleTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testChangeEnglishLocalTranslationOfUsLocale(): void
    {
        $migration = new Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale();
        $migration->update($this->connection);

        $enLangId = $this->fetchLanguageId($this->connection, 'en-GB');

        if ($enLangId) {
            $locale = $this->fetchLocaleTranslation($enLangId, 'en-us', $this->connection);
            static::assertNotNull($locale);
            static::assertEquals('English (US)', $locale);
        }

        $deLangId = $this->fetchLanguageId($this->connection, 'de-DE');

        if ($deLangId) {
            $locale = $this->fetchLocaleTranslation($deLangId, 'en-us', $this->connection);
            static::assertNotNull($locale);
            static::assertEquals('Englisch (US)', $locale);
        }
    }

    private function fetchLocaleTranslation(string $languageId, string $code, Connection $connection): ?string
    {
        return $connection->fetchOne(
            'SELECT `locale_translation`.`name`
             FROM `locale_translation`
                 INNER JOIN `locale` ON `locale_translation`.`locale_id` = `locale`.`id`
             WHERE LOWER(code) = LOWER(:code)
               AND `language_id` = :languageId
             LIMIT 1',
            [
                'code' => $code,
                'languageId' => $languageId,
            ]
        ) ?: null;
    }
}
