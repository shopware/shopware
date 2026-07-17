<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1784276129RepairPasswordChangedMailTranslations extends MigrationStep
{
    private const WRONG_GERMAN_NAME = 'Kunden-Password geändert';

    private const CORRECT_GERMAN_NAME = 'Kunden-Passwort geändert';

    public function getCreationTimestamp(): int
    {
        return 1784276129;
    }

    public function update(Connection $connection): void
    {
        $mailTemplateTypeId = $connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => CustomerPasswordChangedEvent::EVENT_NAME],
        );

        if (!\is_string($mailTemplateTypeId)) {
            return;
        }

        $connection->executeStatement(
            'UPDATE `mail_template_type_translation`
             SET `name` = :correctName
             WHERE `mail_template_type_id` = :mailTemplateTypeId AND `name` = :wrongName',
            [
                'mailTemplateTypeId' => $mailTemplateTypeId,
                'wrongName' => self::WRONG_GERMAN_NAME,
                'correctName' => self::CORRECT_GERMAN_NAME,
            ],
        );

        $connection->executeStatement(
            'UPDATE `mail_template_translation`
             SET `subject` = :correctSubject
             WHERE `subject` = :wrongSubject AND `mail_template_id` IN (
                 SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId
             )',
            [
                'mailTemplateTypeId' => $mailTemplateTypeId,
                'wrongSubject' => self::WRONG_GERMAN_NAME,
                'correctSubject' => self::CORRECT_GERMAN_NAME,
            ],
        );
    }
}
