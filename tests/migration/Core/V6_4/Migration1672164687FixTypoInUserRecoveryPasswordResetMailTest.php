<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1672164687FixTypoInUserRecoveryPasswordResetMail;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1672164687FixTypoInUserRecoveryPasswordResetMail
 *
 * @package system-settings
 */
class Migration1672164687FixTypoInUserRecoveryPasswordResetMailTest extends TestCase
{
    public function testEnUserRecoveryRequestTemplateIsUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $enLangId = $this->fetchEnLanguageId($connection);
        static::assertNotNull($enLangId);

        $migration = new Migration1672164687FixTypoInUserRecoveryPasswordResetMail();
        $migration->update($connection);

        $userRecoveryTemplateId = $this->fetchSystemMailTemplateIdFromType($connection);

        // Only assert in case the template is not updated
        if ($userRecoveryTemplateId === null) {
            return;
        }

        static::assertNotNull($userRecoveryTemplateId);

        $sqlString = 'SELECT `subject`, `content_plain`, `content_html` from `mail_template_translation` WHERE `mail_template_id`= :templateId AND `language_id` = :langId AND `updated_at` IS NULL';

        $userRecoveryTemplateTranslation = $connection->fetchAssociative($sqlString, [
            'langId' => $enLangId,
            'templateId' => $userRecoveryTemplateId,
        ]);

        // Only assert in case the template is not updated
        if (!empty($userRecoveryTemplateTranslation)) {
            static::assertEquals($this->getContentHtmlEn(), $userRecoveryTemplateTranslation['content_html']);
            static::assertEquals($this->getContentPlainEn(), $userRecoveryTemplateTranslation['content_plain']);
        }
    }

    private function fetchSystemMailTemplateIdFromType(Connection $connection): ?string
    {
        $templateTypeId = $connection->executeQuery('
        SELECT `id` from `mail_template_type` WHERE `technical_name` = :type
        ', ['type' => MailTemplateTypes::MAILTYPE_USER_RECOVERY_REQUEST])->fetchOne();

        $templateId = $connection->executeQuery('
        SELECT `id` from `mail_template` WHERE `mail_template_type_id` = :typeId AND `system_default` = 1 AND `updated_at` IS NULL
        ', ['typeId' => $templateTypeId])->fetchOne();

        if ($templateId === false || !\is_string($templateId)) {
            return null;
        }

        return $templateId;
    }

    private function fetchEnLanguageId(Connection $connection): ?string
    {
        return $connection->fetchOne('
        SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`locale_id` = `locale`.`id` WHERE `code` = :code LIMIT 1
        ', ['code' => 'en-GB']) ?: null;
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
