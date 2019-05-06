<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriber;
use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\NewsletterReceiver\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1557130167StandardMailTemplates extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1557130167;
    }

    public function update(Connection $connection): void
    {
        $definitionMailTypes = [
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER => [
                'id' => Uuid::randomHex(),
                'name' => 'Customer registration',
                'nameDe' => 'Kunden Registrierung',
            ],
        ];

        $this->createMailType($connection, $definitionMailTypes);

        $mailTemplateTypes = $connection->executeQuery(
            'SELECT technical_name, id FROM mail_template_type'
        )->fetchAll(FetchMode::ASSOCIATIVE);

        $mailTemplateTypeMapping = [];
        foreach ($mailTemplateTypes as $mailTemplateType) {
            $mailTemplateTypeMapping[$mailTemplateType['technical_name']] = $mailTemplateType['id'];
        }

        $customerRegistrationTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerRegistrationTemplateId,
                'mail_template_type_id' => $mailTemplateTypeMapping[
                MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER
                ],
                'system_default' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $customerRegistrationTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'subject' => 'Your Registration at {{ salesChannel.name }}',
                'description' => '',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateEn(),
                'content_plain' => $this->getRegistrationPlainTemplateEn(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $customerRegistrationTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
                'subject' => 'Deine Registrierung bei {{ salesChannel.name }}',
                'description' => '',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateDe(),
                'content_plain' => $this->getRegistrationPlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => CustomerRegisterEvent::EVENT_NAME,
                'action_name' => MailSendSubscriber::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex(
                        $mailTemplateTypeMapping[MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER]
                    ),
                ]),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => NewsletterRegisterEvent::EVENT_NAME,
                'action_name' => MailSendSubscriber::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex(
                        $mailTemplateTypeMapping[NewsletterSubscriptionServiceInterface::MAIL_TYPE_OPT_IN]
                    ),
                ]),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'event_action',
            [
                'id' => Uuid::randomBytes(),
                'event_name' => NewsletterConfirmEvent::EVENT_NAME,
                'action_name' => MailSendSubscriber::ACTION_NAME,
                'config' => json_encode([
                    'mail_template_type_id' => Uuid::fromBytesToHex(
                        $mailTemplateTypeMapping[NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER]
                    ),
                ]),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }

    /**
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     */
    public function createMailType(Connection $connection, $definitionMailTypes)
    {
        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        foreach ($definitionMailTypes as $typeName => $mailType) {
            $connection->insert(
                'mail_template_type',
                [
                    'id' => Uuid::fromHexToBytes($mailType['id']),
                    'technical_name' => $typeName,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['name'],
                    'language_id' => $languageEn,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );
            $connection->insert(
                'mail_template_type_translation',
                [
                    'mail_template_type_id' => Uuid::fromHexToBytes($mailType['id']),
                    'name' => $mailType['nameDe'],
                    'language_id' => $languageDe,
                    'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                ]
            );

            $sql = <<<SQL
            UPDATE `mail_template`
                SET `mail_template_type_id` = UNHEX(:type_id)
            WHERE `mail_type` = :technical_name;
SQL;
            $connection->executeQuery($sql, ['type_id' => $mailType['id'], 'technical_name' => $typeName]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getRegistrationHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Dear {{ customer.salutation }} {{ customer.lastname }},<br/>
                <br/>
                thank you for your registration with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return 'Dear {{ customer.salutation }} {{ customer.lastname }},
                
                thank you for your registration with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.        
        ';
    }

    private function getRegistrationHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Hallo {{ customer.salutation }} {{ customer.lastname }},<br/>
                <br/>
                vielen Dank für Ihre Anmeldung in unserem Shop.<br/>
                Sie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{{ customer.email }}</strong> und dem von Ihnen gewählten Kennwort.<br/>
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateDe(): string
    {
        return 'Hallo {{ customer.salutation }} {{ customer.lastname }},
                
                vielen Dank für Ihre Anmeldung in unserem Shop.
                Sie erhalten Zugriff über Ihre E-Mail-Adresse {{ customer.email }} und dem von Ihnen gewählten Kennwort.
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
';
    }
}
