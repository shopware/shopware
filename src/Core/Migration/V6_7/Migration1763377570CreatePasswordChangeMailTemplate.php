<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('checkout')]
class Migration1763377570CreatePasswordChangeMailTemplate extends MigrationStep
{
    use UpdateMailTrait;

    private const GERMAN_LANGUAGE_ISO = 'de-DE';

    private const ENGLISH_LANGUAGE_ISO = 'en-GB';

    public function getCreationTimestamp(): int
    {
        return 1763377570;
    }

    public function update(Connection $connection): void
    {
        try {
            $connection->beginTransaction();

            $mailTemplateTypeId = $this->insertMailTemplateTypeData($connection);
            $this->insertMailTemplateData($mailTemplateTypeId, $connection);
            $this->updateMailTemplateContent($connection);

            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private function insertMailTemplateTypeData(Connection $connection): string
    {
        $existingMailTemplateTypeId = $connection->fetchOne('SELECT id FROM mail_template_type WHERE technical_name = :technicalName', ['technicalName' => CustomerPasswordChangedEvent::EVENT_NAME]);

        if ($existingMailTemplateTypeId) {
            return \is_string($existingMailTemplateTypeId) ? $existingMailTemplateTypeId : '';
        }

        $templateTypeId = Uuid::randomBytes();
        $connection->insert('mail_template_type', [
            'id' => $templateTypeId,
            'technical_name' => CustomerPasswordChangedEvent::EVENT_NAME,
            'available_entities' => json_encode(['customer' => 'customer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByIso(self::ENGLISH_LANGUAGE_ISO, $connection);
        $germanLanguageId = $this->fetchLanguageIdByIso(self::GERMAN_LANGUAGE_ISO, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => 'Customer password changed',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $englishLanguageId,
                    'name' => 'Customer password changed',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $germanLanguageId,
                    'name' => 'Kunden-Password geändert',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }

        return $templateTypeId;
    }

    private function insertMailTemplateData(string $templateTypeId, Connection $connection): void
    {
        $existingMailTemplateTypeId = $connection->fetchOne('SELECT id FROM mail_template WHERE mail_template_type_id = :id', ['id' => $templateTypeId]);

        if ($existingMailTemplateTypeId) {
            return;
        }

        $templateId = Uuid::randomBytes();
        $connection->insert(
            'mail_template',
            [
                'id' => $templateId,
                'mail_template_type_id' => $templateTypeId,
                'system_default' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByIso(self::ENGLISH_LANGUAGE_ISO, $connection);
        $germanLanguageId = $this->fetchLanguageIdByIso(self::GERMAN_LANGUAGE_ISO, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Customer password changed',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $defaultLanguageId,
                ]
            );
        }

        if ($englishLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Customer password changed',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $englishLanguageId,
                ]
            );
        }

        if ($germanLanguageId) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Kunden-Password geändert',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => '',
                    'content_plain' => '',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $germanLanguageId,
                ]
            );
        }
    }

    private function fetchLanguageIdByIso(string $iso, Connection $connection): ?string
    {
        try {
            $result = $connection->fetchOne(
                'SELECT language.id
                 FROM `language`
                 INNER JOIN locale ON locale.id = language.translation_code_id
                 WHERE locale.code = :iso',
                ['iso' => $iso]
            );

            if (!\is_string($result)) {
                return null;
            }

            return $result;
        } catch (\Throwable) {
            return null;
        }
    }

    private function updateMailTemplateContent(Connection $connection): void
    {
        $filesystem = new Filesystem();

        $update = new MailUpdate(CustomerPasswordChangedEvent::EVENT_NAME);
        $update->setEnPlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.password.changed/en-plain.html.twig'));
        $update->setEnHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.password.changed/en-html.html.twig'));
        $update->setDePlain($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.password.changed/de-plain.html.twig'));
        $update->setDeHtml($filesystem->readFile(__DIR__ . '/../Fixtures/mails/customer.password.changed/de-html.html.twig'));

        $this->updateMail($update, $connection);
    }
}
