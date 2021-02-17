<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1607500561UpdateSignUpMailTemplateTranslation;

class Migration1607500561UpdateSignUpMailTemplateTranslationTest extends TestCase
{
    use KernelTestBehaviour;

    public function testEnSignupMailTemplateIsUpdated(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        /** @var string $enLangId */
        $enLangId = $this->fetchEnLanguageId($connection);

        static::assertNotNull($enLangId);

        $migration = new Migration1607500561UpdateSignUpMailTemplateTranslation();
        $migration->update($connection);

        /** @var string $signUpTemplateId */
        $signUpTemplateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER);
        static::assertNotNull($signUpTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $signUpTemplateTranslation = $connection->fetchAssoc($sqlString, [
            'langId' => $enLangId,
            'templateId' => $signUpTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($signUpTemplateTranslation)) {
            static::assertEquals('Your sign-up with {{ salesChannel.translated.name }}', $signUpTemplateTranslation['subject']);
            static::assertEquals($this->getSignupHtmlTemplateEn(), $signUpTemplateTranslation['content_html']);
            static::assertEquals($this->getSignupPlainTemplateEn(), $signUpTemplateTranslation['content_plain']);
        }

        $deLangId = $this->fetchDeLanguageId($connection);

        $signUpTemplateDeTranslation = $connection->fetchAssoc($sqlString, [
            'langId' => $deLangId,
            'templateId' => $signUpTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($signUpTemplateDeTranslation)) {
            static::assertEquals($this->getSignupHtmlTemplateDe(), $signUpTemplateDeTranslation['content_html']);
            static::assertEquals($this->getSignupPlainTemplateDe(), $signUpTemplateDeTranslation['content_plain']);
        }

        /** @var string $signUpConfirmationTemplateId */
        $signUpConfirmationTemplateId = $this->fetchSystemMailTemplateIdFromType($connection, MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN);
        static::assertNotNull($signUpTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :enLangId AND `updated_at` IS NULL';

        $signUpConfirmationTemplateTranslation = $connection->fetchAssoc($sqlString, [
            'enLangId' => $enLangId,
            'templateId' => $signUpConfirmationTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($signUpConfirmationTemplateTranslation)) {
            static::assertEquals('Please confirm your sign-up with {{ salesChannel.translated.name }}', $signUpConfirmationTemplateTranslation['subject']);
            static::assertEquals($this->getSignupConfirmationHtmlTemplateEn(), $signUpConfirmationTemplateTranslation['content_html']);
            static::assertEquals($this->getSignupConfirmationPlainTemplateEn(), $signUpConfirmationTemplateTranslation['content_plain']);
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection, string $mailTemplateType): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => $mailTemplateType])->fetchColumn();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchColumn();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
    }

    private function fetchDeLanguageId(Connection $connection): ?string
    {
        return $connection->fetchColumn('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'de-DE']) ?: null;
    }

    private function getSignupConfirmationHtmlTemplateEn(): string
    {
        return '
            <div style="font-family:arial; font-size:12px;">
                <p>
                    Hello {{ customer.salutation.translated.displayName }} {{ customer.lastName }},<br/>
                    <br/>
                    thank you for your signing up with {{ salesChannel.translated.name }}.<br/>
                    Please confirm the sign-up via the following link:<br/>
                    <br/>
                    <a href="{{ confirmUrl }}">Completing sign-up</a><br/>
                    <br/>
                    By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
                </p>
            </div>
        ';
    }

    private function getSignupConfirmationPlainTemplateEn(): string
    {
        return '
            Hello {{ customer.salutation.translated.displayName }} {{ customer.lastName }},

            thank you for your signing up with {{ salesChannel.translated.name }}.
            Please confirm the sign-up via the following link:

            {{ confirmUrl }}

            By this confirmation, you also agree that we may send you further emails as part of the fulfillment of the contract.
        ';
    }

    private function getSignupHtmlTemplateEn(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                {{ customer.salutation.translated.letterName }} {{ customer.firstName }} {{ customer.lastName }},<br/>
                <br/>
                thank you for your signing up with our Shop.<br/>
                You will gain access via the email address <strong>{{ customer.email }}</strong> and the password you have chosen.<br/>
                You can change your password anytime.
            </p>
        </div>';
    }

    private function getSignupPlainTemplateEn(): string
    {
        return '{{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

                thank you for your signing up with our Shop.
                You will gain access via the email address {{ customer.email }} and the password you have chosen.
                You can change your password anytime.
        ';
    }

    private function getSignupHtmlTemplateDe(): string
    {
        return '<div style="font-family:arial; font-size:12px;">
            <p>
                {{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},<br/>
                <br/>
                vielen Dank für Ihre Anmeldung in unserem Shop.<br/>
                Sie erhalten Zugriff über Ihre E-Mail-Adresse <strong>{{ customer.email }}</strong> und dem von Ihnen gewählten Kennwort.<br/>
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
            </p>
        </div>';
    }

    private function getSignupPlainTemplateDe(): string
    {
        return '{{ customer.salutation.translated.letterName }} {{customer.firstName}} {{ customer.lastName }},

                vielen Dank für Ihre Anmeldung in unserem Shop.
                Sie erhalten Zugriff über Ihre E-Mail-Adresse {{ customer.email }} und dem von Ihnen gewählten Kennwort.
                Sie können Ihr Kennwort jederzeit nachträglich ändern.
';
    }
}
