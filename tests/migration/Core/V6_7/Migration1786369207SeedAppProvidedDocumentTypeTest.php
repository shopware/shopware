<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1786369207SeedAppProvidedDocumentType;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1786369207SeedAppProvidedDocumentType::class)]
class Migration1786369207SeedAppProvidedDocumentTypeTest extends TestCase
{
    private const TECHNICAL_NAME = 'app_provided';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1786369207, (new Migration1786369207SeedAppProvidedDocumentType())->getCreationTimestamp());
    }

    public function testSeedAppProvidedDocumentType(): void
    {
        $documentTypeId = $this->connection->fetchOne(
            'SELECT id FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => self::TECHNICAL_NAME]
        );

        if ($documentTypeId) {
            $this->connection->executeStatement(
                'DELETE FROM `document_type_translation` WHERE document_type_id = :documentTypeId',
                ['documentTypeId' => $documentTypeId]
            );

            $this->connection->executeStatement(
                'DELETE FROM `document_type` WHERE technical_name = :technicalName',
                ['technicalName' => self::TECHNICAL_NAME]
            );
        }

        $migration = new Migration1786369207SeedAppProvidedDocumentType();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $documentTypes = $this->connection->fetchAllAssociative(
            'SELECT * FROM document_type WHERE technical_name = :technicalName',
            ['technicalName' => self::TECHNICAL_NAME]
        );

        static::assertCount(1, $documentTypes);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT name, LOWER(HEX(language_id)) as language_id
                 FROM document_type_translation
                 WHERE document_type_id = :documentTypeId',
            ['documentTypeId' => $documentTypes[0]['id']]
        );

        $namesByLanguageId = array_column($translations, 'name', 'language_id');

        static::assertArrayHasKey(Defaults::LANGUAGE_SYSTEM, $namesByLanguageId);
        static::assertSame('App document', $namesByLanguageId[Defaults::LANGUAGE_SYSTEM]);

        $germanLanguageId = $this->connection->fetchOne('
            SELECT LOWER(HEX(`language`.id))
            FROM `language`
            INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` AND `locale`.`code` = :locale
        ', ['locale' => 'de-DE']);

        if ($germanLanguageId !== false) {
            static::assertArrayHasKey($germanLanguageId, $namesByLanguageId);
            static::assertSame('App-Dokument', $namesByLanguageId[$germanLanguageId]);
        }
    }
}
