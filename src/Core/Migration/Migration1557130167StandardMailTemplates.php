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
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.basicInformation.email',
            'configuration_value' => 'doNotReply@localhost',
            'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
        ]);

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
                'description' => 'Registration confirmation',
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
                'description' => 'Registrierungsbestätigung',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getRegistrationHtmlTemplateDe(),
                'content_plain' => $this->getRegistrationPlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $passwordChangeTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $passwordChangeTemplateId,
                'mail_template_type_id' => $mailTemplateTypeMapping[
                MailTemplateTypes::MAILTYPE_PASSWORD_CHANGE
                ],
                'system_default' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Password reset - {{ salesChannel.name }}',
                'description' => 'Password reset request',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getPasswordChangeHtmlTemplateEn(),
                'content_plain' => $this->getPasswordChangePlainTemplateEn(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $passwordChangeTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Password zurücksetzen - {{ salesChannel.name }}',
                'description' => 'Passwort zurücksetzen Anfrage',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getPasswordChangeHtmlTemplateDe(),
                'content_plain' => $this->getPasswordChangePlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $passwordChangeTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
            ]
        );

        $customerGroupChangeAcceptedTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerGroupChangeAcceptedTemplateId,
                'mail_template_type_id' => $mailTemplateTypeMapping[
                MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_ACCEPT
                ],
                'system_default' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Your merchant account has been unlocked - {{ salesChannel.name }}',
                'description' => 'Customer Group Change accepted',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeAcceptedHtmlTemplateEn(),
                'content_plain' => $this->getCustomerGroupChangeAcceptedPlainTemplateEn(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $customerGroupChangeAcceptedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Ihr Händleraccount wurde freigeschaltet - {{ salesChannel.name }}',
                'description' => 'Kundengruppenwechsel freigeschaltet',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeAcceptedHtmlTemplateDe(),
                'content_plain' => $this->getCustomerGroupChangeAcceptedPlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $customerGroupChangeAcceptedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
            ]
        );

        $customerGroupChangeRejectedTemplateId = Uuid::randomBytes();

        $connection->insert(
            'mail_template',
            [
                'id' => $customerGroupChangeRejectedTemplateId,
                'mail_template_type_id' => $mailTemplateTypeMapping[
                MailTemplateTypes::MAILTYPE_CUSTOMER_GROUP_CHANGE_REJECT
                ],
                'system_default' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Your trader account has not been accepted - {{ salesChannel.name }}',
                'description' => 'Customer Group Change rejected',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeRejectedHtmlTemplateEn(),
                'content_plain' => $this->getCustomerGroupChangeRejectedPlainTemplateEn(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $customerGroupChangeRejectedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'subject' => 'Ihr Händleraccountantrag wurde abgelehnt - {{ salesChannel.name }}',
                'description' => 'Kundengruppenwechsel abgelehnt',
                'sender_name' => '{{ salesChannel.name }}',
                'content_html' => $this->getCustomerGroupChangeRejectedHtmlTemplateDe(),
                'content_plain' => $this->getCustomerGroupChangeRejectedPlainTemplateDe(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
                'mail_template_id' => $customerGroupChangeRejectedTemplateId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE),
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
                Dear {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                <br/>
                thank you for your registration with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateEn(): string
    {
        return 'Dear {{ customer.salutation.displayName }} {{ customer.lastName }},
                
                thank you for your registration with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.        
        ';
    }

    private function getRegistrationHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
                <br/>
                vielen Dank für Ihre Anmeldung in unserem Shop.<br/>
                Sie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{{ customer.email }}</strong> und dem von Ihnen gewählten Kennwort.<br/>
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
            </p>
        </div>';
    }

    private function getRegistrationPlainTemplateDe(): string
    {
        return 'Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},
                
                vielen Dank für Ihre Anmeldung in unserem Shop.
                Sie erhalten Zugriff über Ihre E-Mail-Adresse {{ customer.email }} und dem von Ihnen gewählten Kennwort.
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
';
    }

    private function getPasswordChangeHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Dear {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
        <br/>
        there has been a request to reset you Password in the Shop {{ salesChannel.name }}
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Reset passwort</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateEn(): string
    {
        return '
        Dear {{ customer.salutation.displayName }} {{ customer.lastName }},

        there has been a request to reset you Password in the Shop {{ salesChannel.name }}
        Please confirm the link below to specify a new password.

        Reset password: {{ urlResetPassword }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
    ';
    }

    private function getPasswordChangeHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},<br/>
        <br/>
        im Shop {{ salesChannel.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.<br/>
        <br/>
        <a href="{{ urlResetPassword }}">Passwort zurücksetzen</a><br/>
        <br/>
        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
    </p>
</div>';
    }

    private function getPasswordChangePlainTemplateDe(): string
    {
        return '
        Hallo {{ customer.salutation.displayName }} {{ customer.lastName }},
    
        im Shop {{ salesChannel.name }} wurde eine Anfrage gestellt, um Ihr Passwort zurück zu setzen.
        Bitte bestätigen Sie den unten stehenden Link, um ein neues Passwort zu definieren.

        Passwort zurücksetzen: {{ urlResetPassword }}

        Dieser Link ist nur für die nächsten 2 Stunden gültig. Danach muss das Zurücksetzen des Passwortes erneut beantragt werden.
        Falls Sie Ihr Passwort nicht zurücksetzen möchten, ignorieren Sie diese E-Mail - es wird dann keine Änderung vorgenommen.
';
    }

    private function getCustomerGroupChangeAcceptedHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hello,<br/>
        <br/>
        your merchant account at {{ salesChannel.name }} has been unlocked.<br/>
        From now on, we will charge you the net purchase price.
    </p>
</div>';
    }

    private function getCustomerGroupChangeAcceptedPlainTemplateEn(): string
    {
        return '
        Hello,

        your merchant account at {{ salesChannel.name }} has been unlocked.
        From now on, we will charge you the net purchase price.
    ';
    }

    private function getCustomerGroupChangeAcceptedHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo,<br/>
        <br/>
        ihr Händlerkonto bei {{ salesChannel.name }} wurde freigeschaltet.<br/>
        Von nun an werden wir Ihnen den Netto-Preis berechnen.
    </p>
</div>';
    }

    private function getCustomerGroupChangeAcceptedPlainTemplateDe(): string
    {
        return '
        Hallo,
    
        ihr Händlerkonto bei {{ salesChannel.name }} wurde freigeschaltet.
        Von nun an werden wir Ihnen den Netto-Preis berechnen.
    ';
    }

    private function getCustomerGroupChangeRejectedHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hello,<br/>
		<br/>
        thank you for your interest in our trade prices. 
        Unfortunately, we do not have a trading license yet so that we cannot accept you as a merchant.<br/>
        In case of further questions please do not hesitate to contact us via telephone, fax or email.
    </p>
</div>';
    }

    private function getCustomerGroupChangeRejectedPlainTemplateEn(): string
    {
        return '
        Hello,

        thank you for your interest in our trade prices. Unfortunately, 
        we do not have a trading license yet so that we cannot accept you as a merchant.
        In case of further questions please do not hesitate to contact us via telephone, fax or email.
    ';
    }

    private function getCustomerGroupChangeRejectedHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
    <p>
        Hallo,<br/>
        <br/>
        elen Dank für ihr Interesse an unseren Großhandelspreisen. Leider liegt uns bisher keine <br/>
        Händlerauthentifizierung vor, und daher können wir Ihre Anfrage nicht bestätigen. <br/>
        Bei weiteren Fragen kontaktieren Sie uns gerne per Telefon, Fax oder E-Mail. <br/>
    </p>
</div>';
    }

    private function getCustomerGroupChangeRejectedPlainTemplateDe(): string
    {
        return '
        Hallo,

        vielen Dank für ihr Interesse an unseren Großhandelspreisen. Leider liegt uns bisher keine 
        Händlerauthentifizierung vor, und daher können wir Ihre Anfrage nicht bestätigen.
        Bei weiteren Fragen kontaktieren Sie uns gerne per Telefon, Fax oder E-Mail.
    ';
    }
}
