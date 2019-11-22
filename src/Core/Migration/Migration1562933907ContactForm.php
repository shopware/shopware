<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1562933907ContactForm extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562933907;
    }

    public function update(Connection $connection): void
    {
        $contactFormEmailTemplate = [
            'id' => Uuid::randomHex(),
            'name' => 'Contact form',
            'nameDe' => 'Kontaktformular',
            'availableEntities' => json_encode(['salesChannel' => 'sales_channel']),
        ];

        $mailTemplateTypeId = Uuid::fromHexToBytes($contactFormEmailTemplate['id']);
        $connection->insert(
            'mail_template_type',
            [
                'id' => $mailTemplateTypeId,
                'technical_name' => MailTemplateTypes::MAILTYPE_CONTACT_FORM,
                'available_entities' => $contactFormEmailTemplate['availableEntities'],
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $mailTemplateTypeId,
                'name' => $contactFormEmailTemplate['name'],
                'language_id' => $this->getLanguageIdByLocale($connection, 'en-GB'),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_type_translation',
            [
                'mail_template_type_id' => $mailTemplateTypeId,
                'name' => $contactFormEmailTemplate['nameDe'],
                'language_id' => $this->getLanguageIdByLocale($connection, 'de-DE'),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => ContactFormEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode(['mail_template_type_id' => $contactFormEmailTemplate['id']]),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): string
    {
        $sql = <<<SQL
SELECT `language`.`id` 
FROM `language` 
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        /** @var string|false $languageId */
        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchColumn();
        if (!$languageId) {
            throw new \RuntimeException(sprintf('Language for locale "%s" not found.', $locale));
        }

        return $languageId;
    }
}
