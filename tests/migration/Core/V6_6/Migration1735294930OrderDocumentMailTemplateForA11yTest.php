<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1735294930OrderDocumentMailTemplateForA11y;

/**
 * @internal
 */
#[CoversClass(Migration1735294930OrderDocumentMailTemplateForA11y::class)]
class Migration1735294930OrderDocumentMailTemplateForA11yTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrate(): void
    {
        $this->rollback();

        $a11yMailTemplate = $this->connection->fetchAllAssociative(
            '
            SELECT mail_template.*, mail_template_translation.*
            FROM `mail_template`
            INNER JOIN `mail_template_translation` ON mail_template.id = mail_template_translation.mail_template_id
            WHERE mail_template.mail_template_type_id = (SELECT id FROM `mail_template_type` WHERE technical_name = "a11y_mail")'
        );

        static::assertEmpty($a11yMailTemplate);

        $this->migrate();

        $a11yMailTemplate = $this->connection->fetchAllAssociative(
            '
            SELECT mail_template.*, mail_template_translation.*
            FROM `mail_template`
            INNER JOIN `mail_template_translation` ON mail_template.id = mail_template_translation.mail_template_id
            WHERE mail_template.mail_template_type_id = (SELECT id FROM `mail_template_type` WHERE technical_name = "a11y_mail")'
        );

        static::assertNotEmpty($a11yMailTemplate);
        static::assertCount(2, $a11yMailTemplate);
    }

    private function migrate(): void
    {
        (new Migration1735294930OrderDocumentMailTemplateForA11y())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('
            DELETE mail_template_translation, mail_template, mail_template_type
            FROM `mail_template_translation`
            INNER JOIN `mail_template` ON mail_template_translation.mail_template_id = mail_template.id
            INNER JOIN `mail_template_type` ON mail_template.mail_template_type_id = mail_template_type.id
            WHERE mail_template_type.technical_name = "a11y_mail"
        ');
    }
}
