<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Core\Migration\Traits\UpdateMailTrait;

/**
 * @internal
 */
#[Package('system-settings')]
class Migration1672164687FixTypoInUserRecoveryPasswordResetMail extends MigrationStep
{
    use UpdateMailTrait;

    public function getCreationTimestamp(): int
    {
        return 1672164687;
    }

    public function update(Connection $connection): void
    {
        $update = new MailUpdate(
            MailTemplateTypes::MAILTYPE_USER_RECOVERY_REQUEST,
            $this->getContentPlainEn(),
            $this->getContentHtmlEn()
        );

        $this->updateEnMail($connection, $update);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function getContentHtmlEn(): string
    {
        return <<<MAIL
<div style="font-family:arial; font-size:12px;">
    <p>
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},<br/>
        <br/>
        there has been a request to reset your password.
        Please confirm the link below to specify a new password.<br/>
        <br/>
        <a href="{{ resetUrl }}">Reset password</a><br/>
        <br/>
        This link is valid for the next 2 hours. After that you have to request a new confirmation link.<br/>
        If you do not want to reset your password, please ignore this email. No changes will be made.
    </p>
</div>
MAIL;
    }

    private function getContentPlainEn(): string
    {
        return <<<MAIL
        Dear {{ userRecovery.user.firstName }} {{ userRecovery.user.lastName }},

        there has been a request to reset your password.
        Please confirm the link below to specify a new password.

        Reset password: {{ resetUrl }}

        This link is valid for the next 2 hours. After that you have to request a new confirmation link.
        If you do not want to reset your password, please ignore this email. No changes will be made.
MAIL;
    }
}
