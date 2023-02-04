<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1604669773UpdateMailTemplate;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1604669773UpdateMailTemplate
 */
class Migration1604669773UpdateMailTemplateTest extends TestCase
{
    use MigrationTestTrait;

    private const INITIAL = 'initial';

    private const PLAIN_EN = <<<'EOF'
The following Message was sent to you via the contact form.

Contact name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
Contact email address: {{ contactFormData.email }}
Phone: {{ contactFormData.phone }}

Subject: {{ contactFormData.subject }}

Message:
{{ contactFormData.comment }}
EOF;

    private const PLAIN_DE = <<<'EOF'
Folgende Nachricht wurde an Sie via Kontakt-Formular gesendet.

Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
Kontakt E-Mail: {{ contactFormData.email }}

Telefonnummer: {{ contactFormData.phone }}

Betreff: {{ contactFormData.subject }}

Nachricht:
{{ contactFormData.comment }}
EOF;

    private const HTML_EN = <<<'EOF'
<div style="font-family:arial; font-size:12px;">
    <p>
        The following Message was sent to you via the contact form.<br/>
        <br/>
        Contact name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
        <br/>
        Contact email address: {{ contactFormData.email }}
        <br/>
        Phone: {{ contactFormData.phone }}<br/>
        <br/>
        Subject: {{ contactFormData.subject }}<br/>
        <br/>
        Message:<br/>
        {{ contactFormData.comment|nl2br }}<br/>
    </p>
</div>
EOF;

    private const HTML_DE = <<<'EOF'
<div style="font-family:arial; font-size:12px;">
    <p>
        Folgende Nachricht wurde an Sie via Kontakt-Formular gesendet.<br/>
        <br/>
        Name: {{ contactFormData.firstName }} {{ contactFormData.lastName }}
        <br/>
        Kontakt E-Mail: {{ contactFormData.email }}<br/>
        <br>
        Telefonnummer: {{ contactFormData.phone }}<br/>
        <br/>
        Betreff: {{ contactFormData.subject }}<br/>
        <br/>
        Message:<br/>
        {{ contactFormData.comment|nl2br }}<br/>
    </p>
</div>
EOF;

    public function testMailUpdateSuccessful(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1604669773UpdateMailTemplate();

        $this->resetMails();

        $migration->update($connection);

        $mails = $this->getMails();
        $contentPlain = [self::PLAIN_EN, self::PLAIN_DE];
        $contentHtml = [self::HTML_EN, self::HTML_DE];

        // Assert the two plain text templates were updated
        static::assertCount(2, array_filter($mails, static fn (array $mail): bool => \in_array(trim((string) $mail['content_plain']), $contentPlain, true)));

        // Assert the two html templates were updated
        static::assertCount(2, array_filter($mails, static fn (array $mail): bool => \in_array(trim((string) $mail['content_html']), $contentHtml, true)));
    }

    public function testDoesNotOverwriteModifiedTemplates(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1604669773UpdateMailTemplate();

        $this->resetMails();

        $oldMails = array_filter($this->getMails(), static fn (array $mail): bool => $mail['technical_name'] === MailTemplateTypes::MAILTYPE_CONTACT_FORM);

        $connection->executeStatement(
            'UPDATE `mail_template_translation` SET `updated_at` = :timestamp WHERE `mail_template_id` = UNHEX(:mailTemplateId);',
            [
                'timestamp' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'mailTemplateId' => array_unique(array_column($oldMails, 'mail_template_id'))[0],
            ]
        );

        $migration->update($connection);

        $newMails = array_filter($this->getMails(), static fn (array $mail): bool => $mail['technical_name'] === MailTemplateTypes::MAILTYPE_CONTACT_FORM);

        // Assert no template was updated, when it's been modified before
        static::assertSame($oldMails, $newMails);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getMails(): array
    {
        $sql = <<<'EOF'
SELECT
    CONCAT(LOWER(HEX(mail_template.id)), '.', LOWER(HEX(mail_template_translation.language_id))) as `array_key`,
    LOWER(HEX(mail_template.id)) as `mail_template_id`,
    LOWER(HEX(mail_template_translation.language_id)) as `language_id`,
    mail_template_type.technical_name,
    mail_template_translation.content_html,
    mail_template_translation.content_plain
FROM mail_template
    INNER JOIN mail_template_translation
        ON mail_template.id = mail_template_translation.mail_template_id
    INNER JOIN mail_template_type
        ON mail_template.mail_template_type_id = mail_template_type.id
EOF;

        $mails = KernelLifecycleManager::getConnection()
            ->fetchAllAssociative($sql);

        return FetchModeHelper::groupUnique($mails);
    }

    private function resetMails(): void
    {
        KernelLifecycleManager::getConnection()
            ->executeStatement(
                'UPDATE `mail_template_translation` SET `content_plain` = :content, `content_html` = :content;',
                [
                    'content' => self::INITIAL,
                ]
            );
    }
}
