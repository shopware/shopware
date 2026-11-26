<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\V6_7\Migration1795685958UpdateMailTemplatesWithSalutationFallback;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1795685958UpdateMailTemplatesWithSalutationFallback::class)]
class Migration1795685958UpdateMailTemplatesWithSalutationFallbackTest extends MailTemplateMigrationTestCase
{
    private const MAIL_TEMPLATE_TYPES = [
        'cancellation_mail',
        'credit_note_mail',
        'customer.group.registration.accepted',
        'customer.group.registration.declined',
        'customer.password.changed',
        'delivery_mail',
        'downloads_delivery',
        'guest_order.double_opt_in',
        'invoice_mail',
        'order.payment_method.changed',
        'order.state.cancelled',
        'order.state.completed',
        'order.state.in_progress',
        'order.state.open',
        'order_confirmation_mail',
        'order_delivery.state.cancelled',
        'order_delivery.state.returned',
        'order_delivery.state.returned_partially',
        'order_delivery.state.shipped',
        'order_delivery.state.shipped_partially',
        'order_transaction.state.authorized',
        'order_transaction.state.cancelled',
        'order_transaction.state.chargeback',
        'order_transaction.state.failed',
        'order_transaction.state.open',
        'order_transaction.state.paid',
        'order_transaction.state.paid_partially',
        'order_transaction.state.refunded',
        'order_transaction.state.refunded_partially',
        'order_transaction.state.reminded',
        'order_transaction.state.unconfirmed',
        'password_change',
    ];

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1795685958, (new Migration1795685958UpdateMailTemplatesWithSalutationFallback())->getCreationTimestamp());
    }

    public function testMigrationUpdatesUnmodifiedMailTemplates(): void
    {
        $this->prepareDefaultMailTemplates(null);

        $migration = new Migration1795685958UpdateMailTemplatesWithSalutationFallback();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $expected = new MailUpdate($mailTemplateType);
            $expected->loadByDirectoryName($mailTemplateType);

            $translations = $this->getMailTemplateTranslations($mailTemplateType)->translations;

            static::assertSame($expected->getEnPlain(), $translations->getEnPlain(), $mailTemplateType . ': en plain');
            static::assertSame($expected->getEnHtml(), $translations->getEnHtml(), $mailTemplateType . ': en html');
            static::assertSame($expected->getDePlain(), $translations->getDePlain(), $mailTemplateType . ': de plain');
            static::assertSame($expected->getDeHtml(), $translations->getDeHtml(), $mailTemplateType . ': de html');
        }
    }

    public function testMigrationKeepsMailTemplatesModifiedByMerchants(): void
    {
        $this->prepareDefaultMailTemplates('2026-11-26 12:00:00.000');

        (new Migration1795685958UpdateMailTemplatesWithSalutationFallback())->update($this->connection);

        foreach (self::MAIL_TEMPLATE_TYPES as $mailTemplateType) {
            $translations = $this->getMailTemplateTranslations($mailTemplateType)->translations;

            static::assertSame('BEFORE-plain', $translations->getEnPlain(), $mailTemplateType . ': en plain');
            static::assertSame('BEFORE-html', $translations->getEnHtml(), $mailTemplateType . ': en html');
            static::assertSame('BEFORE-plain', $translations->getDePlain(), $mailTemplateType . ': de plain');
            static::assertSame('BEFORE-html', $translations->getDeHtml(), $mailTemplateType . ': de html');
        }
    }

    private function prepareDefaultMailTemplates(?string $translationUpdatedAt): void
    {
        $this->connection->executeStatement(
            '
            UPDATE `mail_template` AS `template`
            INNER JOIN `mail_template_type` AS `type`
                ON `template`.`mail_template_type_id` = `type`.`id`
            SET `template`.`updated_at` = NULL
            WHERE `type`.`technical_name` IN (:technicalNames)
            ',
            ['technicalNames' => self::MAIL_TEMPLATE_TYPES],
            ['technicalNames' => ArrayParameterType::STRING],
        );

        $this->connection->executeStatement(
            '
            UPDATE `mail_template_translation` AS `translation`
            INNER JOIN `mail_template` AS `template`
                ON `translation`.`mail_template_id` = `template`.`id`
            INNER JOIN `mail_template_type` AS `type`
                ON `template`.`mail_template_type_id` = `type`.`id`
            SET `translation`.`updated_at` = :updatedAt,
                `translation`.`content_html` = :contentHtml,
                `translation`.`content_plain` = :contentPlain
            WHERE `type`.`technical_name` IN (:technicalNames)
            ',
            [
                'updatedAt' => $translationUpdatedAt,
                'contentHtml' => 'BEFORE-html',
                'contentPlain' => 'BEFORE-plain',
                'technicalNames' => self::MAIL_TEMPLATE_TYPES,
            ],
            ['technicalNames' => ArrayParameterType::STRING],
        );
    }
}
