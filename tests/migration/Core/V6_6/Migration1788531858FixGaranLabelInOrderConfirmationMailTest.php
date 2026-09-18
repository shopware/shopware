<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1788531858FixGaranLabelInOrderConfirmationMail;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1788531858FixGaranLabelInOrderConfirmationMail::class)]
class Migration1788531858FixGaranLabelInOrderConfirmationMailTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/';

    private Connection $connection;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $originalMailTranslations;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->originalMailTranslations = $this->fetchMailTranslations();
    }

    protected function tearDown(): void
    {
        foreach ($this->originalMailTranslations as $languageId => $translation) {
            $this->connection->update(
                'mail_template_translation',
                $translation,
                ['language_id' => $languageId, 'mail_template_id' => $this->getMailTemplateId()]
            );
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1788531858, (new Migration1788531858FixGaranLabelInOrderConfirmationMail())->getCreationTimestamp());
    }

    public function testMigrationReappliesOrderConfirmationMailTemplate(): void
    {
        $this->givenTheStoredTemplateIsOutdated();

        $migration = new Migration1788531858FixGaranLabelInOrderConfirmationMail();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $filesystem = new Filesystem();
        $translations = $this->fetchMailTranslationsByLanguageName();

        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'en-plain.html.twig'), $translations['English']['content_plain']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'en-html.html.twig'), $translations['English']['content_html']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'de-plain.html.twig'), $translations['Deutsch']['content_plain']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'de-html.html.twig'), $translations['Deutsch']['content_html']);
    }

    public function testMigratedTemplateRendersTheGaranLabelWithFixedDimensionsInsideTheProductCell(): void
    {
        $this->givenTheStoredTemplateIsOutdated();

        (new Migration1788531858FixGaranLabelInOrderConfirmationMail())->update($this->connection);

        $translations = $this->fetchMailTranslationsByLanguageName();

        foreach (['en' => $translations['English']['content_html'], 'de' => $translations['Deutsch']['content_html']] as $language => $html) {
            static::assertIsString($html);
            static::assertStringContainsString('sw_garan_label_nested_uri', $html, $language);
            static::assertStringContainsString(
                '<img src="{{ garanLabelDataUri }}" width="195" height="30"',
                $html,
                $language . ': the label image needs explicit dimensions, mail clients scale an SVG data URI to the container otherwise'
            );
            static::assertStringNotContainsString(
                '<td colspan="6"><img src="{{ garanLabelDataUri }}"',
                $html,
                $language . ': the label belongs into the product cell, not into a full width row of its own'
            );
            static::assertStringNotContainsString(
                'src="{{ garanLabelDataUri }}" alt=""',
                $html,
                $language . ': the label carries legally required information and must not be marked as decorative'
            );
        }
    }

    /**
     * Seeds the broken markup that shops which already ran Migration1783944800AddGaranLabel carry, and
     * clears `updated_at` so the template counts as untouched by the merchant.
     */
    private function givenTheStoredTemplateIsOutdated(): void
    {
        $this->connection->executeStatement(
            'UPDATE `mail_template` AS `template`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             SET `template`.`updated_at` = NULL
             WHERE `type`.`technical_name` = :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]
        );

        $this->connection->executeStatement(
            'UPDATE `mail_template_translation` AS `translation`
             INNER JOIN `mail_template` AS `template` ON `translation`.`mail_template_id` = `template`.`id`
             INNER JOIN `mail_template_type` AS `type` ON `template`.`mail_template_type_id` = `type`.`id`
             SET `translation`.`updated_at` = NULL,
                 `translation`.`content_html` = :contentHtml,
                 `translation`.`content_plain` = :contentPlain
             WHERE `type`.`technical_name` = :technicalName',
            [
                'contentHtml' => '<tr><td colspan="6"><img src="{{ garanLabelDataUri }}" alt="" /></td></tr>',
                'contentPlain' => 'OUTDATED-plain',
                'technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM,
            ]
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchMailTranslations(): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `language_id`, `content_html`, `content_plain`, `updated_at` FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            ['mailTemplateId' => $this->getMailTemplateId()]
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchMailTranslationsByLanguageName(): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `language`.`name`, `translation`.`content_html`, `translation`.`content_plain`
             FROM `mail_template_translation` AS `translation`
             INNER JOIN `language` ON `language`.`id` = `translation`.`language_id`
             WHERE `translation`.`mail_template_id` = :mailTemplateId AND `language`.`name` IN (:names)',
            ['mailTemplateId' => $this->getMailTemplateId(), 'names' => ['English', 'Deutsch']],
            ['names' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );
    }

    private function getMailTemplateId(): string
    {
        $mailTemplateTypeId = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => MailTemplateTypes::MAILTYPE_ORDER_CONFIRM]
        );

        return (string) $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );
    }
}
