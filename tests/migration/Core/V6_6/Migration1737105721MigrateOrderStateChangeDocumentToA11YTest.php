<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\V6_6\Migration1737105721MigrateOrderStateChangeDocumentToA11Y;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1737105721MigrateOrderStateChangeDocumentToA11Y::class)]
class Migration1737105721MigrateOrderStateChangeDocumentToA11YTest extends TestCase
{
    use ImportTranslationsTrait;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testDuplicateMigration(): void
    {
        $migration = new Migration1737105721MigrateOrderStateChangeDocumentToA11Y();
        static::assertSame(1737105721, $migration->getCreationTimestamp());

        // make sure a migration can run multiple times without failing
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    #[DataProvider('mailTypeProvider')]
    public function testMigration(string $mailType): void
    {
        $this->prepareData($mailType);

        $migration = new Migration1737105721MigrateOrderStateChangeDocumentToA11Y();
        $migration->update($this->connection);

        $documentTypeTranslationMapping = $this->getMailTemplateType();

        foreach ($documentTypeTranslationMapping as $technicalName) {
            $mailTemplateId = $this->connection->fetchOne('
                SELECT `mail_template`.`id`
                FROM `mail_template`
                INNER JOIN `mail_template_type`
                    ON `mail_template`.`mail_template_type_id` = `mail_template_type`.`id`
                    AND `mail_template_type`.`technical_name` = :technicalName
           ', ['technicalName' => $technicalName]);

            if (!$mailTemplateId) {
                continue;
            }

            /** @var array{id: string, content_html: string, content_plain: string}|null $mailTemplate */
            $mailTemplate = $this->connection->fetchAssociative(
                '
                SELECT `mail_template`.`id`, `mail_template_translation`.`content_html`, `mail_template_translation`.`content_plain`
                FROM `mail_template`
                INNER JOIN `mail_template_translation`
                    ON `mail_template`.`id` = `mail_template_translation`.`mail_template_id`
                WHERE `mail_template`.`id` = :mailTemplateId',
                ['mailTemplateId' => $mailTemplateId],
            );

            static::assertNotNull($mailTemplate);

            if ($technicalName === $mailType) {
                static::assertStringNotContainsString('{% if a11yDocuments', $mailTemplate['content_html']);
                static::assertStringNotContainsString('{% if a11yDocuments', $mailTemplate['content_plain']);
            } else {
                static::assertStringContainsString('{% if a11yDocuments', $mailTemplate['content_html']);
                static::assertStringContainsString('{% for a11y in a11yDocuments %}', $mailTemplate['content_html']);

                static::assertStringContainsString('{% if a11yDocuments', $mailTemplate['content_plain']);
                static::assertStringContainsString('{% for a11y in a11yDocuments %}', $mailTemplate['content_plain']);
            }
        }
    }

    /**
     * @return array<string, array<string>>
     */
    public static function mailTypeProvider(): array
    {
        return [
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS],
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED => [MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED],
        ];
    }

    private function prepareData(string $mailType): void
    {
        $documentTypeTranslationMapping = $this->getMailTemplateType();

        $this->connection->executeStatement(
            '
                UPDATE `mail_template`
                SET `updated_at` = :updatedAt
                WHERE `mail_template_type_id` IN (
                    SELECT `id`
                    FROM `mail_template_type`
                    WHERE `technical_name` IN (:technicalNames)
                )',
            [
                'updatedAt' => null,
                'technicalNames' => $documentTypeTranslationMapping,
            ],
            ['technicalNames' => ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            '
            UPDATE `mail_template_translation`
            SET `content_html` = :contentHtml,
                `content_plain` = :contentPlain
            WHERE `mail_template_id` IN (
                SELECT `id`
                FROM `mail_template`
                WHERE `mail_template_type_id` IN (
                    SELECT `id`
                    FROM `mail_template_type`
                    WHERE `technical_name` IN (:technicalNames)
                )
            )',
            [
                'contentHtml' => 'html content',
                'contentPlain' => 'plain content',
                'technicalNames' => $documentTypeTranslationMapping,
            ],
            ['technicalNames' => ArrayParameterType::STRING],
        );

        $this->connection->executeStatement('
            UPDATE `mail_template`
            SET `updated_at` = :updatedAt
            WHERE `mail_template_type_id` IN (
                SELECT `id`
                FROM `mail_template_type`
                WHERE `technical_name` = :technicalName
            )
        ', [
            'updatedAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'technicalName' => $mailType,
        ]);
    }

    /**
     * @return array<string>
     */
    private function getMailTemplateType(): array
    {
        return [
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REMINDED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_OPEN,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_RETURNED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_DELIVERY_STATE_SHIPPED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CANCELLED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_REFUNDED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_PAID_PARTIALLY,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_AUTHORIZED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_CHARGEBACK,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_TRANSACTION_STATE_UNCONFIRMED,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_OPEN,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_IN_PROGRESS,
            MailTemplateTypes::MAILTYPE_STATE_ENTER_ORDER_STATE_COMPLETED,
        ];
    }
}
