<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1652345915UpdateSubjectMailTemplate;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1652345915UpdateSubjectMailTemplate
 */
class Migration1652345915UpdateSubjectMailTemplateTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $this->connection->executeStatement(
            '
            DELETE FROM mail_template_type where technical_name=:technicalName',
            [
                'technicalName' => MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            ]
        );

        $mailTemplateTypeId = Uuid::randomBytes();
        $this->connection->insert('mail_template_type', [
            'id' => $mailTemplateTypeId,
            'technical_name' => MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $mailTemplateId = Uuid::randomBytes();
        $this->connection->insert('mail_template', [
            'id' => $mailTemplateId,
            'mail_template_type_id' => $mailTemplateTypeId,
            'system_default' => 1,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('mail_template_translation', [
            'mail_template_id' => $mailTemplateId,
            'subject' => 'Your order with {{ salesChannel.name }} is delivered',
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        (new Migration1652345915UpdateSubjectMailTemplate())->update($this->connection);
        static::assertNull($this->getSubject());
    }

    public function getSubject(): ?string
    {
        return $this->connection->fetchOne('
            SELECT mail_template_translation.subject
            FROM mail_template_translation
            WHERE mail_template_translation.subject=:wrongSubject
            AND mail_template_translation.mail_template_id=(
            SELECT mail_template.id
            FROM mail_template_type
            JOIN mail_template ON mail_template_type.id = mail_template.mail_template_type_id
            WHERE mail_template_type.technical_name=:mailTemplateType);
        ', [
            'wrongSubject' => 'Your order with {{ salesChannel.name }} is delivered',
            'mailTemplateType' => MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
        ]) ?: null;
    }
}
