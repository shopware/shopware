<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('services-settings')]
class Migration1690874168FixPaymentStatusUnconfirmedMail extends MigrationStep
{
    use UpdateMailTrait;

    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    public function getCreationTimestamp(): int
    {
        return 1690874168;
    }

    public function update(Connection $connection): void
    {
        $templateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :name', ['name' => Migration1688106315AddMissingTransactionMailTemplates::UNCONFIRMED_TYPE]);
        $templateId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);

        $languageId = $connection->fetchOne(
            'SELECT id FROM `language` WHERE `name` = :name',
            ['name' => self::GERMAN_LANGUAGE_NAME]
        );

        if (!\is_string($languageId)) {
            return;
        }

        $this->updateMailTemplateTranslation($connection, $templateId, $languageId);
        $this->updateMailTemplateTypeTranslation($connection, $templateTypeId, $languageId);
    }

    private function updateMailTemplateTranslation(Connection $connection, string $templateId, string $languageId): void
    {
        $connection->update(
            'mail_template_translation',
            ['subject' => 'Ihre Bestellung bei {{ salesChannel.name }} ist unbestätigt'],
            ['mail_template_id' => $templateId, 'language_id' => $languageId],
        );
    }

    private function updateMailTemplateTypeTranslation(Connection $connection, string $templateTypeId, string $languageId): void
    {
        $connection->update(
            'mail_template_type_translation',
            ['name' => 'Eintritt Zahlungsstatus: Unbestätigt'],
            ['mail_template_type_id' => $templateTypeId, 'language_id' => $languageId],
        );
    }
}
