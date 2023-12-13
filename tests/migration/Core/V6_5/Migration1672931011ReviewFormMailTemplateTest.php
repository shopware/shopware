<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1672931011ReviewFormMailTemplate;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[CoversClass(Migration1672931011ReviewFormMailTemplate::class)]
class Migration1672931011ReviewFormMailTemplateTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testNewMailTemplatesAreAdded(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertNull($mailTemplateTypeId);

        // Rerun migration and make sure everything is added again
        $migration = new Migration1672931011ReviewFormMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertNotNull($mailTemplateTypeId);
        static::assertNotNull($this->getMailTemplateId($mailTemplateTypeId));
    }

    public function testNewMailTemplatesAreAddedWithoutGermanAndEnglishLanguage(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        static::assertNull($this->getMailTemplateTypeId());

        $this->changeDefaultLanguageToDutch();

        // Rerun migration and make sure everything is added again
        $migration = new Migration1672931011ReviewFormMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertNotNull($mailTemplateTypeId);
        static::assertNotNull($this->getMailTemplateId($mailTemplateTypeId));
    }

    public function testMigrationWithExistingTemplateData(): void
    {
        $this->rollbackMigrationChanges();

        // Add invoice for example
        $existingTypeId = Uuid::randomBytes();
        $this->connection->insert(
            'mail_template_type',
            [
                'id' => $existingTypeId,
                'technical_name' => MailTemplateTypes::MAILTYPE_REVIEW_FORM,
                'available_entities' => json_encode(['salesChannel' => 'sales_channel']),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $existingTypeId,
                'name' => 'New product review',
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $migration = new Migration1672931011ReviewFormMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertNotNull($mailTemplateTypeId);
        static::assertNotNull($this->getMailTemplateId($mailTemplateTypeId));
    }

    public function testNewMailTemplatesAreAddedWithGermanAsDefault(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        static::assertEmpty($this->getMailTemplateTypeId());

        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');
        $this->connection->executeStatement('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');

        $languageId = $this->createNewLanguageEntry('de-DE');
        $this->swapDefaultLanguageId($languageId);

        // Rerun migration and make sure everything is added again
        $migration = new Migration1672931011ReviewFormMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertNotNull($mailTemplateTypeId);
        static::assertNotNull($this->getMailTemplateId($mailTemplateTypeId));
    }

    private function rollbackMigrationChanges(): void
    {
        $typeId = $this->getMailTemplateTypeId();

        if (!$typeId) {
            return;
        }

        $templateId = $this->getMailTemplateId($typeId);

        if ($templateId) {
            $this->deleteRowsByReferencedId(
                $templateId,
                'mail_template_translation',
                'mail_template_id'
            );

            $this->deleteRowsByReferencedId(
                $templateId,
                'mail_template',
                'id'
            );
        }

        $this->deleteRowsByReferencedId(
            $typeId,
            'mail_template_type_translation',
            'mail_template_type_id'
        );

        $this->deleteRowsByReferencedId(
            $typeId,
            'mail_template_type',
            'id'
        );
    }

    private function getMailTemplateTypeId(): ?string
    {
        $typeId = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('mail_template_type')
            ->where('technical_name = :name')
            ->setParameter('name', MailTemplateTypes::MAILTYPE_REVIEW_FORM)
            ->fetchOne();

        if (\is_string($typeId)) {
            return $typeId;
        }

        return null;
    }

    private function getMailTemplateId(string $typeId): ?string
    {
        $templateId = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('mail_template')
            ->where('mail_template_type_id = :id')
            ->setParameter('id', $typeId)
            ->fetchOne();

        if (\is_string($templateId)) {
            return $templateId;
        }

        return null;
    }

    private function deleteRowsByReferencedId(string $associatedId, string $table, string $associationField): void
    {
        $query = $this->connection->createQueryBuilder()
            ->delete($table)
            ->orWhere(\sprintf('%s = :id', $associationField))
            ->setParameter('id', $associatedId, ParameterType::BINARY)
            ->executeStatement();
    }

    private function changeDefaultLanguageToDutch(): void
    {
        $languageId = $this->createNewLanguageEntry('nl-NL');
        $this->swapDefaultLanguageId($languageId);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0;');
        $this->connection->executeStatement('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    private function createNewLanguageEntry(string $iso): string
    {
        $id = Uuid::randomBytes();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER (HEX(locale.id))
            FROM `locale`
            WHERE LOWER(locale.code) = LOWER(?)'
        );
        $localeId = $stmt->executeQuery([$iso])->fetchOne();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER(language.id)
            FROM `language`
            WHERE LOWER(language.name) = LOWER(?)'
        );
        $englishId = $stmt->executeQuery(['english'])->fetchOne();

        $stmt = $this->connection->prepare(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?'
        );
        // Always use the English name since we dont have the name in the language itself
        $name = $stmt->executeQuery([$localeId, $englishId])->fetchOne();

        $stmt = $this->connection->prepare(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id, created_at)
            VALUES
            (?,?,UNHEX(?),UNHEX(?), ?)'
        );

        $stmt->executeStatement([$id, $name, $localeId, $localeId, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

        return $id;
    }

    private function swapDefaultLanguageId(string $newLanguageId): void
    {
        $stmt = $this->connection->prepare(
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
