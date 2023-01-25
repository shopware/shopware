<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailSubjectUpdate;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('core')]
class Migration1638514913RemovedUnusedVarsInMailTemplates extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1638514913;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            UPDATE `mail_template_translation`
            SET `description` = \'Anfrage zum Zurücksetzen des Passworts\'
            WHERE `description` = \'Passwort zurücksetzen Anfrage\'
            AND `updated_at` IS NULL;
        ');

        $connection->executeStatement(sprintf('
            UPDATE `mail_template_type`
            SET `available_entities` = REPLACE(`available_entities`, \'urlResetPassword\', \'resetUrl\')
            WHERE `technical_name` = \'%s\'
        ', MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE));

        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE,
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/password_change/en-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/password_change/en-html.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/password_change/de-plain.html.twig'),
            (string) file_get_contents(__DIR__ . '/../Fixtures/mails/password_change/de-html.html.twig'),
        );
        $this->updateMail($update, $connection);

        $update = new MailSubjectUpdate(
            MailTemplateTypes::MAILTYPE_USER_RECOVERY_REQUEST,
            null,
            'Password-Wiederherstellung'
        );
        $this->updateDeMailSubject($connection, $update);

        $update = new MailSubjectUpdate(
            MailTemplateTypes::MAILTYPE_CUSTOMER_RECOVERY_REQUEST,
            null,
            'Password-Wiederherstellung'
        );
        $this->updateDeMailSubject($connection, $update);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
