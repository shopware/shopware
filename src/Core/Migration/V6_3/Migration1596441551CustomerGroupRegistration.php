<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1596441551CustomerGroupRegistration extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1596441551;
    }

    public function update(Connection $connection): void
    {
        $this->updateCustomerTable($connection);
        $this->createTables($connection);
        $this->createMailTypes($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    public function createMailTypes(Connection $connection): void
    {
        $enLangId = $this->fetchLanguageId('en-GB', $connection);
        $deLangId = $this->fetchLanguageId('de-DE', $connection);

        $types = [
            'customer.group.registration.accepted' => [
                'de-DE' => 'Kunden Gruppen Registrierung Akzeptiert',
                'en-GB' => 'Customer Group Registration Accepted',
            ],
            'customer.group.registration.declined' => [
                'de-DE' => 'Kunden Gruppen Registrierung Abgelehnt',
                'en-GB' => 'Customer Group Registration Declined',
            ],
        ];

        foreach ($types as $typeName => $translations) {
            $typeId = Uuid::randomBytes();

            $connection->insert('mail_template_type', [
                'id' => $typeId,
                'technical_name' => $typeName,
                'available_entities' => json_encode(['customer' => 'customer', 'customerGroup' => 'customer_group']),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            $languageIds = [];

            if ($enLangId) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $typeId,
                    'language_id' => $enLangId,
                    'name' => $translations['en-GB'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);

                $languageIds[] = Uuid::fromBytesToHex($enLangId);
            }

            if ($deLangId) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $typeId,
                    'language_id' => $deLangId,
                    'name' => $translations['de-DE'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);

                $languageIds[] = Uuid::fromBytesToHex($deLangId);
            }

            // We don't have both en and de
            if (!\in_array(Defaults::LANGUAGE_SYSTEM, $languageIds, true)) {
                $connection->insert('mail_template_type_translation', [
                    'mail_template_type_id' => $typeId,
                    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'name' => $translations['en-GB'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]);
            }

            $this->createMailTemplates($connection, $translations, $typeName, $typeId, $enLangId, $deLangId);
        }
    }

    private function updateCustomerTable(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `customer`
ADD `requested_customer_group_id` binary(16) NULL AFTER `customer_group_id`;');

        $connection->executeStatement('ALTER TABLE `customer`
ADD INDEX `fk.customer.requested_customer_group_id` (`requested_customer_group_id`);');
    }

    private function createTables(Connection $connection): void
    {
        $connection->executeStatement('
ALTER TABLE `customer_group`
ADD `registration_active` tinyint(1) NOT NULL DEFAULT \'0\' AFTER `display_gross`;
');
        $connection->executeStatement('
ALTER TABLE `customer_group_translation`
ADD `registration_title` varchar(255) NULL AFTER `custom_fields`,
ADD `registration_introduction` longtext NULL AFTER `registration_title`,
ADD `registration_only_company_registration` tinyint(1) NULL AFTER `registration_introduction`,
ADD `registration_seo_meta_description` longtext NULL AFTER `registration_only_company_registration`;
');
    }

    private function fetchLanguageId(string $code, Connection $connection): ?string
    {
        /** @var string|null $langId */
        $langId = $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => $code]);

        if (!$langId) {
            return null;
        }

        return $langId;
    }

    /**
     * @param array{'en-GB': string, 'de-DE': string} $typeTranslations
     */
    private function createMailTemplates(Connection $connection, array $typeTranslations, string $typeName, string $typeId, ?string $enLangId, ?string $deLangId): void
    {
        $mailTemplateContent = require __DIR__ . '/../Fixtures/MailTemplateContent.php';
        $mailTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $mailTemplateId,
                'mail_template_type_id' => $typeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $languageIds = [];

        if ($enLangId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailTemplateId,
                    'language_id' => $enLangId,
                    'subject' => $typeTranslations['en-GB'],
                    'description' => '',
                    'sender_name' => 'Shop',
                    'content_html' => $mailTemplateContent[$typeName]['en-GB']['html'],
                    'content_plain' => $mailTemplateContent[$typeName]['en-GB']['plain'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $languageIds[] = Uuid::fromBytesToHex($enLangId);
        }

        if ($deLangId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailTemplateId,
                    'language_id' => $deLangId,
                    'subject' => $typeTranslations['de-DE'],
                    'description' => '',
                    'sender_name' => 'Shop',
                    'content_html' => $mailTemplateContent[$typeName]['de-DE']['html'],
                    'content_plain' => $mailTemplateContent[$typeName]['de-DE']['plain'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );

            $languageIds[] = Uuid::fromBytesToHex($deLangId);
        }

        // We don't have both en and de
        if (!\in_array(Defaults::LANGUAGE_SYSTEM, $languageIds, true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'mail_template_id' => $mailTemplateId,
                    'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                    'subject' => $typeTranslations['en-GB'],
                    'description' => '',
                    'sender_name' => 'Shop',
                    'content_html' => $mailTemplateContent[$typeName]['en-GB']['html'],
                    'content_plain' => $mailTemplateContent[$typeName]['en-GB']['plain'],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => $typeName,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex($typeId),
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }
}
