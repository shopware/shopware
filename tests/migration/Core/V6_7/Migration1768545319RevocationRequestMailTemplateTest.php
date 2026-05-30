<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1768545319RevocationRequestMailTemplate;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1768545319RevocationRequestMailTemplate::class)]
class Migration1768545319RevocationRequestMailTemplateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1768545319, (new Migration1768545319RevocationRequestMailTemplate())->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->removePreinstalled(MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT);
        $this->removePreinstalled(MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER);

        $migration = new Migration1768545319RevocationRequestMailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $this->assertIsInstalled(MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_MERCHANT);
        $this->assertIsInstalled(MailTemplateTypes::MAILTYPE_REVOCATION_REQUEST_CUSTOMER);
    }

    private function assertIsInstalled(string $technicalName): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($technicalName);
        $mailTemplateTypeId = $this->assertIsValidByteId($mailTemplateTypeId);

        $typeTranslations = $this->getTranslations('mail_template_type_translation', 'mail_template_type_id', $mailTemplateTypeId);
        static::assertCount(2, $typeTranslations);

        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);
        $mailTemplateId = $this->assertIsValidByteId($mailTemplateId);

        $templateTranslations = $this->getTranslations('mail_template_translation', 'mail_template_id', $mailTemplateId);
        static::assertCount(2, $templateTranslations);
    }

    private function assertIsValidByteId(?string $byteId): string
    {
        static::assertIsString($byteId);
        $id = Uuid::fromBytesToHex($byteId);
        static::assertTrue(Uuid::isValid($id));

        return $byteId;
    }

    private function removePreinstalled(string $technicalName): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($technicalName);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);
        $this->delete('mail_template_translation', $mailTemplateId, 'mail_template_id');
        $this->delete('mail_template', $mailTemplateId);
        $this->delete('mail_template_type_translation', $mailTemplateTypeId, 'mail_template_type_id');
        $this->delete('mail_template_type', $mailTemplateTypeId);
    }

    private function delete(string $table, ?string $id, string $identifier = 'id'): void
    {
        if (!\is_string($id)) {
            return;
        }

        $this->connection->delete($table, [$identifier => $id]);
    }

    private function getMailTemplateTypeId(string $technicalName): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $technicalName]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    private function getMailTemplateId(?string $mailTemplateTypeId): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getTranslations(string $table, string $criteriaKey, string $criteriaValue): array
    {
        $sql = \sprintf('SELECT * FROM `%s` WHERE `%s` = :criteriaValue', $table, $criteriaKey);

        return $this->connection->fetchAllAssociative(
            $sql,
            ['criteriaValue' => $criteriaValue]
        );
    }
}
