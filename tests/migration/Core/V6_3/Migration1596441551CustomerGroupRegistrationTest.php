<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1596441551CustomerGroupRegistration;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1596441551CustomerGroupRegistration
 */
class Migration1596441551CustomerGroupRegistrationTest extends TestCase
{
    use MigrationTestTrait;

    public function testTablesArePresent(): void
    {
        $customerGroupColumns = array_column(KernelLifecycleManager::getConnection()->fetchAllAssociative('SHOW COLUMNS FROM customer_group'), 'Field');
        $customerGroupTranslationColumns = array_column(KernelLifecycleManager::getConnection()->fetchAllAssociative('SHOW COLUMNS FROM customer_group_translation'), 'Field');

        static::assertContains('registration_active', $customerGroupColumns);
        static::assertContains('registration_title', $customerGroupTranslationColumns);
        static::assertContains('registration_introduction', $customerGroupTranslationColumns);
        static::assertContains('registration_only_company_registration', $customerGroupTranslationColumns);
        static::assertContains('registration_seo_meta_description', $customerGroupTranslationColumns);
    }

    public function testMailTypesExists(): void
    {
        $typesCount = (int) KernelLifecycleManager::getConnection()->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name IN(\'customer.group.registration.accepted\', \'customer.group.registration.declined\')');
        static::assertSame(2, $typesCount);
    }

    public function testDutchWithRemovedDeAndEnLanguage(): void
    {
        static::markTestSkipped('NEXT-24549: should be enabled again after NEXT-24549 is fixed');

        $connection = KernelLifecycleManager::getConnection();
        $this->changeDefaultLanguageToDutch($connection);

        $migration = new Migration1596441551CustomerGroupRegistration();
        $migration->createMailTypes($connection);

        $typesCount = (int) KernelLifecycleManager::getConnection()->fetchOne('SELECT COUNT(*) FROM mail_template_type WHERE technical_name IN(\'customer.group.registration.accepted\', \'customer.group.registration.declined\')');
        static::assertSame(2, $typesCount);

        $templates = (int) KernelLifecycleManager::getConnection()->fetchOne('SELECT COUNT(*) FROM mail_template');
        static::assertSame(2, $templates);

        $templateTranslation = (int) KernelLifecycleManager::getConnection()->fetchOne('SELECT COUNT(*) FROM mail_template_translation');
        static::assertSame(2, $templateTranslation);
    }

    private function changeDefaultLanguageToDutch(Connection $connection): void
    {
        $languageId = $this->createNewLanguageEntry($connection, 'nl-NL');
        $this->swapDefaultLanguageId($connection, $languageId);
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');
        $connection->executeStatement('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeStatement('DELETE FROM mail_template_type');
        $connection->executeStatement('DELETE FROM mail_template_type_translation');
        $connection->executeStatement('DELETE FROM mail_template');
        $connection->executeStatement('DELETE FROM mail_template_translation');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function createNewLanguageEntry(Connection $connection, string $iso): string
    {
        $id = Uuid::randomBytes();

        $localeId = $connection->fetchOne(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(:iso)',
            ['iso' => $iso]
        );

        $englishId = $connection->fetchOne(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = "english"'
        );

        //Always use the English name since we dont have the name in the language itself
        $name = $connection->fetchOne(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?',
            [$localeId, $englishId]
        );

        $connection->executeStatement(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id, created_at)
            VALUES
            (?,?,UNHEX(?),UNHEX(?), ?)',
            [$id, $name, $localeId, $localeId, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        return $id;
    }

    private function swapDefaultLanguageId(Connection $connection, string $newLanguageId): void
    {
        $stmt = $connection->prepare(
            'UPDATE language
             SET id = :newId
             WHERE id = :oldId'
        );

        // assign new uuid to old DEFAULT
        $stmt->executeStatement([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->executeStatement([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $newLanguageId,
        ]);
    }
}
