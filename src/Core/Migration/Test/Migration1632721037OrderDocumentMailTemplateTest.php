<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1632721037OrderDocumentMailTemplate;

class Migration1632721037OrderDocumentMailTemplateTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testNewMailTemplatesAreAdded(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        static::assertEmpty($this->getMailTemplateTypeIds());

        // Rerun migration and make sure everything is added again
        $migration = new Migration1632721037OrderDocumentMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeIds = $this->getMailTemplateTypeIds();
        static::assertCount(4, $mailTemplateTypeIds);
        static::assertCount(4, $this->getTemplateIds($mailTemplateTypeIds));
    }

    public function testNewMailTemplatesAreAddedWithoutGermanAndEnglishLanguage(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        static::assertEmpty($this->getMailTemplateTypeIds());

        $this->changeDefaultLanguageToDutch();

        // Rerun migration and make sure everything is added again
        $migration = new Migration1632721037OrderDocumentMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeIds = $this->getMailTemplateTypeIds();
        static::assertCount(4, $mailTemplateTypeIds);
        static::assertCount(4, $this->getTemplateIds($mailTemplateTypeIds));
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
                'technical_name' => MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE,
                'available_entities' => json_encode(['order' => 'order', 'salesChannel' => 'sales_channel']),
                'template_data' => '{"order":{"orderNumber":"10060","orderCustomer":{"firstName":"Max","lastName":"Mustermann"}},"salesChannel":{"name":"Storefront"}}',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $existingTypeId,
                'name' => 'Invoice',
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $migration = new Migration1632721037OrderDocumentMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeIds = $this->getMailTemplateTypeIds();
        static::assertCount(4, $mailTemplateTypeIds);
        static::assertCount(4, $this->getTemplateIds($mailTemplateTypeIds));
    }

    public function testNewMailTemplatesAreAddedWithGermanAsDefault(): void
    {
        // Rollback migration data and make sure its gone
        $this->rollbackMigrationChanges();
        static::assertEmpty($this->getMailTemplateTypeIds());

        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 0;');
        $this->connection->executeUpdate('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 1;');

        $languageId = $this->createNewLanguageEntry('de-DE');
        $this->swapDefaultLanguageId($languageId);

        // Rerun migration and make sure everything is added again
        $migration = new Migration1632721037OrderDocumentMailTemplate();
        $migration->update($this->connection);

        $mailTemplateTypeIds = $this->getMailTemplateTypeIds();
        static::assertCount(4, $mailTemplateTypeIds);
        static::assertCount(4, $this->getTemplateIds($mailTemplateTypeIds));
    }

    private function rollbackMigrationChanges(): void
    {
        $typeIds = $this->getMailTemplateTypeIds();
        $templateIds = $this->getTemplateIds($typeIds);

        // Drop TemplateTranslations, Templates, TypeTranslations, Types
        $this->deleteRowsByReferencedId(
            $templateIds,
            'mail_template_translation',
            'mail_template_id'
        );

        $this->deleteRowsByReferencedId(
            $templateIds,
            'mail_template',
            'id'
        );

        $this->deleteRowsByReferencedId(
            $typeIds,
            'mail_template_type_translation',
            'mail_template_type_id'
        );

        $this->deleteRowsByReferencedId(
            $typeIds,
            'mail_template_type',
            'id'
        );
    }

    private function getMailTemplateTypeIds(): array
    {
        $result = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('mail_template_type')
            ->where('technical_name in (:invoice, :creditNote, :cancellation, :deliveryNote)')
            ->setParameter('invoice', MailTemplateTypes::MAILTYPE_DOCUMENT_INVOICE)
            ->setParameter('creditNote', MailTemplateTypes::MAILTYPE_DOCUMENT_CREDIT_NOTE)
            ->setParameter('cancellation', MailTemplateTypes::MAILTYPE_DOCUMENT_CANCELLATION_INVOICE)
            ->setParameter('deliveryNote', MailTemplateTypes::MAILTYPE_DOCUMENT_DELIVERY_NOTE)
            ->execute()
            ->fetchAllAssociativeIndexed();

        return \array_keys($result);
    }

    private function deleteRowsByReferencedId(array $associatedIds, string $table, string $associationField): void
    {
        $query = $this->connection->createQueryBuilder()->delete($table);

        foreach ($associatedIds as $index => $associatedId) {
            $parameter = \sprintf(':associatedId%s', $index);
            $query->orWhere(\sprintf('%s = %s', $associationField, $parameter))
                ->setParameter($parameter, $associatedId, ParameterType::BINARY);
        }

        $query->execute();
    }

    private function getTemplateIds(array $typeIds): array
    {
        $query = $this->connection->createQueryBuilder()->select('id')->from('mail_template');

        foreach ($typeIds as $index => $typeId) {
            $parameter = \sprintf(':typeId%s', $index);
            $query->orWhere(\sprintf('mail_template_type_id = %s', $parameter))
                ->setParameter($parameter, $typeId, ParameterType::BINARY);
        }

        return \array_keys($query->execute()->fetchAllAssociativeIndexed());
    }

    private function changeDefaultLanguageToDutch(): void
    {
        $languageId = $this->createNewLanguageEntry('nl-NL');
        $this->swapDefaultLanguageId($languageId);
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 0;');
        $this->connection->executeUpdate('DELETE FROM language WHERE id != UNHEX(?)', [Defaults::LANGUAGE_SYSTEM]);
        $this->connection->executeUpdate('SET FOREIGN_KEY_CHECKS = 1;');
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
        $stmt->execute([$iso]);
        $localeId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT LOWER(language.id)
            FROM `language`
            WHERE LOWER(language.name) = LOWER(?)'
        );
        $stmt->execute(['english']);
        $englishId = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            SELECT locale_translation.name
            FROM `locale_translation`
            WHERE LOWER(HEX(locale_id)) = ?
            AND LOWER(language_id) = ?'
        );
        //Always use the English name since we dont have the name in the language itself
        $stmt->execute([$localeId, $englishId]);
        $name = $stmt->fetchColumn();

        $stmt = $this->connection->prepare(
            '
            INSERT INTO `language`
            (id,name,locale_id,translation_code_id, created_at)
            VALUES
            (?,?,UNHEX(?),UNHEX(?), ?)'
        );

        $stmt->execute([$id, $name, $localeId, $localeId, (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT)]);

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
