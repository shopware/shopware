<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Migration\V6_7\Migration1744203319MailTemplate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1744203319MailTemplate::class)]
class Migration1744203319MailTemplateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        // Prepare test
        $mailTemplateTypeId = $this->getTemplateTypeId();
        if ($mailTemplateTypeId !== null) {
            $this->deleteTemplateTypeTranslations($mailTemplateTypeId);
            $this->deleteTemplateType($mailTemplateTypeId);
        }

        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);
        if ($mailTemplateId !== null) {
            $this->deleteMailTemplate($mailTemplateId);
            $this->deleteMailTemplateTranslations($mailTemplateId);
        }

        static::assertFalse($this->checkTemplateTypeExists());
        static::assertSame(0, $this->checkTemplateTypeTranslationsExists($mailTemplateTypeId));
        static::assertFalse($this->checkIdMailTemplateExists($mailTemplateId));
        static::assertSame(0, $this->checkMailTemplateTranslationExists($mailTemplateId));

        // Start with the test
        $migration = new Migration1744203319MailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getTemplateTypeId();
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        static::assertTrue($this->checkTemplateTypeExists());
        static::assertSame(2, $this->checkTemplateTypeTranslationsExists($mailTemplateTypeId));
        static::assertTrue($this->checkIdMailTemplateExists($mailTemplateId));
        static::assertSame(2, $this->checkMailTemplateTranslationExists($mailTemplateId));
    }

    private function checkTemplateTypeExists(): bool
    {
        return (bool) $this->getTemplateTypeId();
    }

    private function checkTemplateTypeTranslationsExists(?string $mailTemplateTypeId): int
    {
        if ($mailTemplateTypeId === null) {
            return 0;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`name`) FROM `mail_template_type_translation` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );
    }

    private function checkIdMailTemplateExists(?string $mailTemplateId): bool
    {
        if ($mailTemplateId === null) {
            return false;
        }

        return (bool) $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `id` = :mailTemplateId',
            ['mailTemplateId' => $mailTemplateId]
        );
    }

    private function checkMailTemplateTranslationExists(?string $mailTemplateId): int
    {
        if ($mailTemplateId === null) {
            return 0;
        }

        return (int) $this->connection->fetchOne(
            'SELECT COUNT(`mail_template_id`) FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            [
                'mailTemplateId' => $mailTemplateId,
            ]
        );
    }

    private function getTemplateTypeId(): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` LIKE "admin_sso_user_invite"'
        );

        if (!$result) {
            return null;
        }

        return $result;
    }

    private function getMailTemplateId(?string $mailTemplateTypeId): ?string
    {
        if ($mailTemplateTypeId === null) {
            return null;
        }

        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$result) {
            return null;
        }

        return $result;
    }

    private function deleteTemplateType(?string $templateTypeId): void
    {
        if ($templateTypeId === null) {
            return;
        }

        $this->connection->delete('mail_template_type', ['id' => $templateTypeId]);
    }

    private function deleteTemplateTypeTranslations(?string $templateTypeId): void
    {
        if ($templateTypeId === null) {
            return;
        }

        $this->connection->delete('mail_template_type_translation', ['mail_template_type_id' => $templateTypeId]);
    }

    private function deleteMailTemplate(?string $mailTemplateId): void
    {
        if ($mailTemplateId === null) {
            return;
        }

        $this->connection->delete('mail_template', ['id' => $mailTemplateId]);
    }

    private function deleteMailTemplateTranslations(?string $mailTemplateId): void
    {
        $this->connection->delete('mail_template_translation', ['mail_template_id' => $mailTemplateId]);
    }
}
