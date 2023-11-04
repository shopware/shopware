<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class Migration1573569685DoubleOptInGuestMailTemplate extends MigrationStep
{
    private const GERMAN_LANGUAGE_NAME = 'Deutsch';

    private const ENGLISH_LANGUAGE_NAME = 'English';

    public function getCreationTimestamp(): int
    {
        return 1573569685;
    }

    public function update(Connection $connection): void
    {
        $templateId = Uuid::randomBytes();
        $templateTypeId = Uuid::randomBytes();

        $this->insertMailTemplateTypeData($templateTypeId, $connection);
        $this->insertMailTemplateData($templateId, $templateTypeId, $connection);
        $this->insertEventActionData($templateTypeId, $connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // nth
    }

    private function fetchLanguageIdByName(string $languageName, Connection $connection): ?string
    {
        try {
            return (string) $connection->fetchOne(
                'SELECT id FROM `language` WHERE `name` = :languageName',
                ['languageName' => $languageName]
            );
        } catch (Exception) {
            return null;
        }
    }

    private function insertMailTemplateTypeData(string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'mail_template_type',
            [
                'id' => $templateTypeId,
                'technical_name' => 'guest_order.double_opt_in',
                'available_entities' => $this->getAvailableEntities(),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $defaultLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => $templateTypeId,
                    'language_id' => $defaultLanguageId,
                    'name' => 'Double opt in guest order',
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
                    'name' => 'Double opt in guest order',
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
                    'name' => 'Double-Opt-In-Gast-Bestellung',
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ]
            );
        }
    }

    private function insertMailTemplateData(string $templateId, string $templateTypeId, Connection $connection): void
    {
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

        $englishLanguageId = $this->fetchLanguageIdByName(self::ENGLISH_LANGUAGE_NAME, $connection);
        $germanLanguageId = $this->fetchLanguageIdByName(self::GERMAN_LANGUAGE_NAME, $connection);

        if (!\in_array($defaultLanguageId, [$englishLanguageId, $germanLanguageId], true)) {
            $connection->insert(
                'mail_template_translation',
                [
                    'subject' => 'Please confirm your email address at {{ salesChannel.name }}',
                    'description' => 'Email confirmation at guest orders',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => $this->getHtmlTemplateEn(),
                    'content_plain' => $this->getPlainTemplateEn(),
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
                    'subject' => 'Please confirm your email address at {{ salesChannel.name }}',
                    'description' => 'Email confirmation at guest orders',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => $this->getHtmlTemplateEn(),
                    'content_plain' => $this->getPlainTemplateEn(),
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
                    'subject' => 'Bitte bestätigen Sie Ihre E-Mail-Adresse bei {{ salesChannel.name }}',
                    'description' => 'Anmeldebestätigung bei Gastbestellungen',
                    'sender_name' => '{{ salesChannel.name }}',
                    'content_html' => $this->getHtmlTemplateDe(),
                    'content_plain' => $this->getPlainTemplateDe(),
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'mail_template_id' => $templateId,
                    'language_id' => $germanLanguageId,
                ]
            );
        }
    }

    private function insertEventActionData(string $templateTypeId, Connection $connection): void
    {
        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => DoubleOptInGuestOrderEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex($templateTypeId),
                ], \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function getHtmlTemplateDe(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                    <br/>
                    Bitte bestätigen Sie Ihre E-Mail-Adresse über den nachfolgenden Link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">E-Mail bestätigen</a><br/>
                    <br/>
                    Nach der Bestätigung werden Sie in den Bestellabschluss geleitet, dort können Sie Ihre Bestellung nochmals überprüfen und abschließen.<br/>
                    Durch diese Bestätigung erklären Sie sich ebenso damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateDe(): string
    {
        return '
            Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},

            Bitte bestätigen Sie Ihre E-Mail-Adresse über den nachfolgenden Link:

            {{ confirmUrl }}

            Nach der Bestätigung werden Sie in den Bestellabschluss geleitet, dort können Sie Ihre Bestellung nochmals überprüfen und abschließen.
            Durch diese Bestätigung erklären Sie sich ebenso damit einverstanden, dass wir Ihnen im Rahmen der Vertragserfüllung weitere E-Mails senden dürfen.
        ';
    }

    private function getHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hello {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                    <br/>
                    Please confirm your email address via the following link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">Confirm email</a><br/>
                    <br/>
                    After the confirmation, you will be directed to the checkout, where you can check and complete your order again.<br/>
                    By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
                </p>
            </div>
        ';
    }

    private function getPlainTemplateEn(): string
    {
        return '
            Hello {{ customer.salutation.displayName }} {{ customer.lastName }},

            Please confirm your email address via the following link:

            {{ confirmUrl }}

            After the confirmation, you will be directed to the checkout, where you can check and complete your order again.
            By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
        ';
    }

    private function getAvailableEntities(): string
    {
        return '{"customer":"customer","salesChannel":"sales_channel"}';
    }
}
