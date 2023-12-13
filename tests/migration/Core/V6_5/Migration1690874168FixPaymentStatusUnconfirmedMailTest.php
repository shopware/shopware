<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1688106315AddMissingTransactionMailTemplates;
use Shopware\Core\Migration\V6_5\Migration1690874168FixPaymentStatusUnconfirmedMail;

/**
 * @internal
 */
#[CoversClass(Migration1690874168FixPaymentStatusUnconfirmedMail::class)]
class Migration1690874168FixPaymentStatusUnconfirmedMailTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $templateTypeId = $this->connection->fetchOne('SELECT HEX(id) FROM mail_template_type WHERE technical_name = :name', ['name' => Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE]);

        if (\is_string($templateTypeId)) {
            $this->connection->executeStatement('DELETE FROM `mail_template` WHERE `mail_template_type_id` = :id', ['id' => $templateTypeId]);
            $this->connection->executeStatement('DELETE FROM `mail_template_type` WHERE `id` = :id', ['id' => $templateTypeId]);
        }
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1690874168FixPaymentStatusUnconfirmedMail();
        static::assertSame(1690874168, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $migration = new Migration1690874168FixPaymentStatusUnconfirmedMail();
        $migration->update($this->connection);
        $migration->update($this->connection);
        $this->assertMailTemplate();
    }

    public function assertMailTemplate(): void
    {
        $languageId = $this->connection->fetchOne('SELECT Hex(id) FROM `language` WHERE name = :name', ['name' => 'Deutsch']);
        $templateTypeId = $this->connection->fetchOne('SELECT Hex(id) FROM `mail_template_type` WHERE technical_name = :name', ['name' => Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE]);
        $templateId = $this->connection->fetchOne('SELECT Hex(id) FROM `mail_template` WHERE mail_template_type_id = :id', ['id' => Uuid::fromHexToBytes($templateTypeId)]);

        $templateSubject = $this->connection->fetchOne('SELECT subject FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId AND language_id = :languageId', ['mailTemplateId' => Uuid::fromHexToBytes($templateId), 'languageId' => Uuid::fromHexToBytes($languageId)]);
        static::assertEquals('Ihre Bestellung bei {{ salesChannel.name }} ist unbestätigt', $templateSubject);

        $templateName = $this->connection->fetchOne('SELECT name FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId AND language_id = :languageId', ['mailTemplateTypeId' => Uuid::fromHexToBytes($templateTypeId), 'languageId' => Uuid::fromHexToBytes($languageId)]);
        static::assertEquals('Eintritt Zahlungsstatus: Unbestätigt', $templateName);
    }

    public function testGermanLanguageDoesNotExist(): void
    {
        $connection = $this->createMock(Connection::class);

        $connection->method('fetchOne')->willReturnOnConsecutiveCalls(
            'test-template-type-id',
            'test-template-id',
            false,
        );

        $connection->expects(static::never())->method('update');

        $migration = new Migration1690874168FixPaymentStatusUnconfirmedMail();
        $migration->update($connection);
    }
}
