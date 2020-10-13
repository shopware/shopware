<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1596441551CustomerGroupRegistration;

class Migration1596441551CustomerGroupRegistrationTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    public function testTablesArePresent(): void
    {
        $customerGroupColumns = array_column($this->getContainer()->get(Connection::class)->fetchAll('SHOW COLUMNS FROM customer_group'), 'Field');
        $customerGroupTranslationColumns = array_column($this->getContainer()->get(Connection::class)->fetchAll('SHOW COLUMNS FROM customer_group_translation'), 'Field');

        static::assertContains('registration_active', $customerGroupColumns);
        static::assertContains('registration_title', $customerGroupTranslationColumns);
        static::assertContains('registration_introduction', $customerGroupTranslationColumns);
        static::assertContains('registration_only_company_registration', $customerGroupTranslationColumns);
        static::assertContains('registration_seo_meta_description', $customerGroupTranslationColumns);
    }

    public function testMailTypesExists(): void
    {
        $typesCount = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM mail_template_type WHERE technical_name IN(\'customer.group.registration.accepted\', \'customer.group.registration.declined\')');
        static::assertSame(2, $typesCount);
    }

    public function testDutchWithRemovedDeAndEnLanguage(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $this->changeDefaultLanguageToDutch($connection);

        $migration = new Migration1596441551CustomerGroupRegistration();
        $migration->createMailTypes($connection);

        $typesCount = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM mail_template_type WHERE technical_name IN(\'customer.group.registration.accepted\', \'customer.group.registration.declined\')');
        static::assertSame(2, $typesCount);

        $templates = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM mail_template');
        static::assertSame(2, $templates);

        $templateTranslation = (int) $this->getContainer()->get(Connection::class)->fetchColumn('SELECT COUNT(*) FROM mail_template_translation');
        static::assertSame(2, $templateTranslation);
    }

    private function changeDefaultLanguageToDutch(Connection $connection): void
    {
        $languageId = $this->createNewLanguageEntry($connection, 'nl-NL');
        $this->swapDefaultLanguageId($connection, $languageId);
        $connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 0;');
        $connection->executeUpdate('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeUpdate('DELETE FROM mail_template_type', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeUpdate('DELETE FROM mail_template_type_translation', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeUpdate('DELETE FROM mail_template', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeUpdate('DELETE FROM mail_template_translation', [Defaults::LANGUAGE_SYSTEM]);
        $connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function createNewLanguageEntry(Connection $connection, string $iso): string
    {
        $id = Uuid::randomBytes();

        $stmt = $connection->prepare(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(?)'
        );
        $stmt->execute([$iso]);
        $localeId = $stmt->fetchColumn();

        $stmt = $connection->prepare(
            '
            SELECT LOWER(language.id)
            FROM `language`
            WHERE LOWER(language.name) = LOWER(?)'
        );
        $stmt->execute(['english']);
        $englishId = $stmt->fetchColumn();

        $stmt = $connection->prepare(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?'
        );
        //Always use the English name since we dont have the name in the language itself
        $stmt->execute([$localeId, $englishId]);
        $name = $stmt->fetchColumn();

        $stmt = $connection->prepare(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id, created_at)
            VALUES
            (?,?,UNHEX(?),UNHEX(?), ?)'
        );

        $stmt->execute([$id, $name, $localeId, $localeId, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

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
        $stmt->execute([
            'newId' => Uuid::randomBytes(),
            'oldId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
        ]);

        // change id to DEFAULT
        $stmt->execute([
            'newId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'oldId' => $newLanguageId,
        ]);
    }
}
