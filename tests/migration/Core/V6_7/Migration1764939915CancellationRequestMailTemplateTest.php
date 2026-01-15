<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1764939915CancellationRequestMailTemplate;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1764939915CancellationRequestMailTemplate::class)]
class Migration1764939915CancellationRequestMailTemplateTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        $mailTemplateIds = $this->getMailTemplateIds($mailTemplateTypeId);
        if (!empty($mailTemplateTypeId)) {
            foreach ($mailTemplateIds as $mailTemplateId) {
                $this->connection->delete('mail_template_translation', ['mail_template_id' => $mailTemplateId]);
                $this->connection->delete('mail_template', ['id' => $mailTemplateId]);
            }
        }

        if (!empty($mailTemplateTypeId)) {
            $this->connection->delete('mail_template_type_translation', ['mail_template_type_id' => $mailTemplateTypeId]);
            $this->connection->delete('mail_template', ['id' => $mailTemplateTypeId]);
        }

        $migration = new Migration1764939915CancellationRequestMailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $mailTemplateTypeId = $this->getMailTemplateTypeId();
        static::assertIsString($mailTemplateTypeId);
        static::assertTrue(Uuid::isValid(Uuid::fromBytesToHex($mailTemplateTypeId)));
        static::assertCount(1, $this->getMailTemplateIds($mailTemplateTypeId));
    }

    private function getMailTemplateTypeId(): ?string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_CANCELLATION_REQUEST_MERCHANT]
        );

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function getMailTemplateIds(?string $mailTemplateTypeId): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );
    }
}
