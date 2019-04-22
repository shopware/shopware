<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\NewsletterReceiver\SalesChannel\NewsletterSubscriptionServiceInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1554204498NewsletterMailTemplate extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1554204498;
    }

    public function update(Connection $connection): void
    {
        $registerMailId = Uuid::randomBytes();
        $confirmMailId = Uuid::randomBytes();

        $languageEn = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDe = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM_DE);

        $connection->insert(
            'mail_template',
            [
                'id' => $registerMailId,
                'sender_mail' => 'info@shopware.com',
                'mail_type' => NewsletterSubscriptionServiceInterface::MAIL_TYPE_OPT_IN,
                'system_default' => true,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $registerMailId,
                'language_id' => $languageEn,
                'subject' => 'Newsletter',
                'description' => '',
                'content_html' => $this->getOptInTemplate_HTML_EN(),
                'content_plain' => $this->getOptInTemplate_PLAIN_EN(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $registerMailId,
                'language_id' => $languageDe,
                'subject' => 'Newsletter',
                'description' => '',
                'content_html' => $this->getOptInTemplate_HTML_DE(),
                'content_plain' => $this->getOptInTemplate_PLAIN_DE(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template',
            [
                'id' => $confirmMailId,
                'sender_mail' => 'info@shopware.com',
                'mail_type' => NewsletterSubscriptionServiceInterface::MAIL_TYPE_REGISTER,
                'system_default' => true,
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $confirmMailId,
                'language_id' => $languageEn,
                'subject' => 'Register',
                'description' => '',
                'content_html' => $this->getRegisterTemplate_HTML_EN(),
                'content_plain' => $this->getRegisterTemplate_PLAIN_EN(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );

        $connection->insert(
            'mail_template_translation',
            [
                'mail_template_id' => $confirmMailId,
                'language_id' => $languageDe,
                'subject' => 'Register',
                'description' => '',
                'content_html' => $this->getRegisterTemplate_HTML_DE(),
                'content_plain' => $this->getRegisterTemplate_PLAIN_DE(),
                'created_at' => date(Defaults::STORAGE_DATE_FORMAT),
            ]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getRegisterTemplate_HTML_EN()
    {
        return '<h3>Hello {{ firstName }} {{ lastName }}</h3>
                <p>thank you very much for your registration.</p>
                <p>You have successfully subscribed to our newsletter.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_EN()
    {
        return 'Hello {{ firstName }} {{ lastName }}
            
                thank you very much for your registration.
            
                You have successfully subscribed to our newsletter.
        ';
    }

    private function getRegisterTemplate_HTML_DE()
    {
        return '<h3>Hallo {{ firstName }} {{ lastName }}</h3>
                <p>vielen Dank für Ihre Anmeldung.</p>
                <p>Sie haben sich erfolgreich zu unserem Newsletter angemeldet.</p>
        ';
    }

    private function getRegisterTemplate_PLAIN_DE()
    {
        return 'Hallo {{ firstName }} {{ lastName }}
            
                vielen Dank für Ihre Anmeldung.
            
                Sie haben sich erfolgreich zu unserem Newsletter angemeldet.
        ';
    }

    private function getOptInTemplate_HTML_EN()
    {
        return '<h3>Hello {{ firstName }} {{ lastName }}</h3>
                <p>Thank you for your interest in our newsletter!</p>
                <p>In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking <a href="{{ url }}">here</a>.</p>
                <p>If you have not subscribed to the newsletter, please ignore this email.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_EN()
    {
        return 'Hello {{ firstName }} {{ lastName }}
        
                Thank you for your interest in our newsletter!
                
                In order to prevent misuse of your email address, we have sent you this confirmation email. Confirm that you wish to receive the newsletter regularly by clicking on the link: {{ url }}
                
                If you have not subscribed to the newsletter, please ignore this email.
        ';
    }

    private function getOptInTemplate_HTML_DE()
    {
        return '<h3>Hallo {{ firstName }} {{ lastName }}</h3>
                <p>Schön, dass Sie sich für unseren Newsletter interessieren!</p>
                <p>Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie <a href="{{ url }}">hier</a> klicken.</p>
                <p>Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.</p>
        ';
    }

    private function getOptInTemplate_PLAIN_DE()
    {
        return 'Hallo {{ firstName }} {{ lastName }}
        
                Schön, dass Sie sich für unseren Newsletter interessieren! 
                
                Um einem Missbrauch Ihrer E-Mail-Adresse vorzubeugen, haben wir Ihnen diese Bestätigungsmail gesendet. Bestätigen Sie, dass Sie den Newsletter regelmäßig erhalten wollen, indem Sie auf den folgenden Link klicken: {{ url }} 
                
                Sollten Sie den Newsletter nicht angefordert haben, ignorieren Sie diese E-Mail.
        ';
    }
}
