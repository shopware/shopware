<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Traits\ImportTranslationsTrait;
use Shopware\Core\Migration\V6_4\Migration1636014089UpdateOrderConfirmationMailTemplates;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1636014089UpdateOrderConfirmationMailTemplates
 */
class Migration1636014089UpdateOrderConfirmationMailTemplatesTest extends TestCase
{
    use MigrationTestTrait;
    use ImportTranslationsTrait;

    private const CODE_EN = 'en-GB';
    private const CODE_DE = 'de-DE';

    private Connection $connection;

    private string $templateTypeId;

    private string $enLanguageId;

    private string $deLanguageId;

    public function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->templateTypeId = $this->fetchSystemMailTemplateIdFromType();
        $this->enLanguageId = $this->getLanguageIds($this->connection, self::CODE_EN)[0];
        $this->deLanguageId = $this->getLanguageIds($this->connection, self::CODE_DE)[0];
    }

    public function testDoesNotOverrideModifiedTemplates(): void
    {
        $this->simulateOldDatabaseEntriesWithUpdatedAt();

        $oldEnMails = $this->fetchMailData($this->enLanguageId);
        $oldDeMails = $this->fetchMailData($this->deLanguageId);

        $migration = new Migration1636014089UpdateOrderConfirmationMailTemplates();
        $migration->update($this->connection);

        $newEnMails = $this->fetchMailData($this->enLanguageId);
        $newDeMails = $this->fetchMailData($this->deLanguageId);

        static::assertSame($oldEnMails, $newEnMails);
        static::assertSame($oldDeMails, $newDeMails);
    }

    public function testUpdateMailsSuccessful(): void
    {
        $plainEN = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-plain.html.twig');
        $htmlEN = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/en-html.html.twig');
        $plainDE = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-plain.html.twig');
        $htmlDE = file_get_contents(__DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/de-html.html.twig');

        $shouldEnMail = [
            [
                'content_plain' => $plainEN,
                'content_html' => $htmlEN,
            ],
        ];

        $shouldDeMail = [
            [
                'content_plain' => $plainDE,
                'content_html' => $htmlDE,
            ],
        ];

        $migration = new Migration1636014089UpdateOrderConfirmationMailTemplates();
        $migration->update($this->connection);

        $enMail = $this->fetchMailData($this->enLanguageId);
        $deMail = $this->fetchMailData($this->deLanguageId);

        static::assertSame($shouldEnMail, $enMail);
        static::assertSame($shouldDeMail, $deMail);
    }

    private function simulateOldDatabaseEntriesWithUpdatedAt(): void
    {
        // English
        $this->connection->update(
            'mail_template_translation',
            [
                'content_plain' => 'TEST',
                'content_html' => 'TEST',
                'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'mail_template_id' => $this->templateTypeId,
                'language_id' => Uuid::fromHexToBytes($this->enLanguageId),
            ]
        );

        // German
        $this->connection->update(
            'mail_template_translation',
            [
                'content_plain' => 'TEST',
                'content_html' => 'TEST',
                'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            [
                'mail_template_id' => $this->templateTypeId,
                'language_id' => Uuid::fromHexToBytes($this->deLanguageId),
            ]
        );
    }

    /**
     * @return array{content_plain: string, content_html: string}[]
     */
    private function fetchMailData(string $languageId): array
    {
        /** @var array{content_plain: string, content_html: string}[] $mailData */
        $mailData = $this->connection->fetchAllAssociative(
            'SELECT `content_plain`, `content_html` FROM `mail_template_translation` WHERE `mail_template_id` = :templateId AND `language_id` = :languageId AND `updated_at` IS NULL',
            [
                'templateId' => $this->templateTypeId,
                'languageId' => Uuid::fromHexToBytes($languageId),
            ]
        );

        return $mailData;
    }

    private function fetchSystemMailTemplateIdFromType(): string
    {
        $templateId = (string) $this->connection->fetchOne('
        SELECT mt.id from `mail_template` AS mt
        INNER JOIN `mail_template_type` AS mtt ON mt.mail_template_type_id = mtt.id
        WHERE mtt.technical_name = "order_confirmation_mail" AND mt.system_default = 1 AND mt.updated_at IS NULL');

        return $templateId;
    }
}
