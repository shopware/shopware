<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

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
class Migration1607500561UpdateSignUpMailTemplateTranslation extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1607500561;
    }

    public function update(Connection $connection): void
    {
        // update customer registration
        $mailUpdate = new MailUpdate(
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER,
            $this->getSignupPlainTemplateEn(),
            $this->getSignupHtmlTemplateEn(),
            $this->getSignupPlainTemplateDe(),
            $this->getSignupHtmlTemplateDe()
        );

        $this->updateMail($mailUpdate, $connection);

        $mailSubjectUpdate = new MailSubjectUpdate(
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER,
            'Your sign-up with {{ salesChannel.translated.name }}'
        );

        $this->updateEnMailSubject($connection, $mailSubjectUpdate);

        // update customer register double opt in
        $mailUpdate = new MailUpdate(
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN,
            $this->getSignupConfirmationPlainTemplateEn(),
            $this->getSignupConfirmationHtmlTemplateEn()
        );
        $this->updateEnMail($connection, $mailUpdate);

        $mailSubjectUpdate = new MailSubjectUpdate(
            MailTemplateTypes::MAILTYPE_CUSTOMER_REGISTER_DOUBLE_OPT_IN,
            'Please confirm your sign-up with {{ salesChannel.translated.name }}'
        );

        $this->updateEnMailSubject($connection, $mailSubjectUpdate);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
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
