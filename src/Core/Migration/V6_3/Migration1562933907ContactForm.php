<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\ContactForm\Event\ContactFormEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
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
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLangId = $this->getLanguageIdByLocale($connection, 'en-GB');
        $deLangId = $this->getLanguageIdByLocale($connection, 'de-DE');

        if ($defaultLangId !== $deLangId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'name' => $contactFormEmailTemplate['name'],
                    'language_id' => $defaultLangId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($defaultLangId !== Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'name' => $contactFormEmailTemplate['name'],
                    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($deLangId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $mailTemplateTypeId,
                    'name' => $contactFormEmailTemplate['nameDe'],
                    'language_id' => $deLangId,
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => ContactFormEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode(['mail_template_type_id' => $contactFormEmailTemplate['id']], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function getLanguageIdByLocale(Connection $connection, string $locale): ?string
    {
        $sql = <<<'SQL'
SELECT `language`.`id`
FROM `language`
INNER JOIN `locale` ON `locale`.`id` = `language`.`locale_id`
WHERE `locale`.`code` = :code
SQL;

        /** @var string|false $languageId */
        $languageId = $connection->executeQuery($sql, ['code' => $locale])->fetchOne();
        if (!$languageId && $locale !== 'en-GB') {
            return null;
        }

        if (!$languageId) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return $languageId;
    }
}
