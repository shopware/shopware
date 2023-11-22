<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale;

/**
 * @internal
 */
#[Package('system-settings')]
#[CoversClass(Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale::class)]
class Migration1675323588ChangeEnglishLocaleTranslationOfUsLocaleTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testChangeLishLocalTranslationOfUsLocale(): void
    {
        $migration = new Migration1675323588ChangeEnglishLocaleTranslationOfUsLocale();
        $migration->update($this->connection);

        $enLangId = $this->fetchLanguageId('en-GB', $this->connection);

        if ($enLangId) {
            $locale = $this->fetchLocaleTranslation($enLangId, 'en-us', $this->connection);
            static::assertNotNull($locale);
            static::assertEquals('English (US)', $locale);
        }

        $deLangId = $this->fetchLanguageId('de-DE', $this->connection);

        if ($deLangId) {
            $locale = $this->fetchLocaleTranslation($deLangId, 'en-us', $this->connection);
            static::assertNotNull($locale);
            static::assertEquals('Englisch (US)', $locale);
        }
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        $langId = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `code` = :code LIMIT 1',
            ['code' => $code]
        );
        if ($langId === false) {
            return null;
        }

        return (string) $langId;
    }

    private function fetchLocaleTranslation(string $languageId, string $code, Connection $connection): ?string
    {
        $locale = $connection->fetchOne(
            'SELECT `locale_translation`.`name` FROM `locale_translation` INNER JOIN `locale` ON `locale_translation`.`locale_id` = `locale`.`id` WHERE LOWER(code) = LOWER(:code) AND `language_id` = :languageId LIMIT 1',
            [
                'code' => $code,
                'languageId' => $languageId,
            ]
        );
        if ($locale === false) {
            return null;
        }

        return (string) $locale;
    }
}
