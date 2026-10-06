<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateTypes;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_6\Migration1790751498ReadGaranLabelFromMailTemplateData;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1790751498ReadGaranLabelFromMailTemplateData::class)]
class Migration1790751498ReadGaranLabelFromMailTemplateDataTest extends TestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../../../../src/Core/Migration/Fixtures/mails/order_confirmation_mail/';

    private const FILTER_HTML = '{% set garanLabel = nestedItem.productId|sw_garan_label_mail(context) %}';

    private const FILTER_PLAIN = '{% set garanLabel = lineItem.productId|sw_garan_label_mail(context) %}';

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
        static::assertSame(1790751498, (new Migration1790751498ReadGaranLabelFromMailTemplateData())->getCreationTimestamp());
    }

    public function testMigrationReplacesTheFilterWithTheTemplateData(): void
    {
        $this->givenTheStoredTemplateUsesTheFilter(editedByMerchant: false);

        $migration = new Migration1790751498ReadGaranLabelFromMailTemplateData();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $filesystem = new Filesystem();
        $translations = $this->fetchMailTranslationsByLanguageName();

        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'en-plain.html.twig'), $translations['English']['content_plain']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'en-html.html.twig'), $translations['English']['content_html']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'de-plain.html.twig'), $translations['Deutsch']['content_plain']);
        static::assertSame($filesystem->readFile(self::FIXTURE_DIR . 'de-html.html.twig'), $translations['Deutsch']['content_html']);

        foreach ($translations as $translation) {
            foreach ([$translation['content_html'], $translation['content_plain']] as $content) {
                static::assertIsString($content);
                static::assertStringContainsString('garanLabels[', $content);
                static::assertStringNotContainsString('sw_garan_label_mail', $content);
            }
        }
    }

    public function testMigrationKeepsTemplatesEditedByTheMerchant(): void
    {
        $this->givenTheStoredTemplateUsesTheFilter(editedByMerchant: true);

        (new Migration1790751498ReadGaranLabelFromMailTemplateData())->update($this->connection);

        $translations = $this->fetchMailTranslationsByLanguageName();

        static::assertSame(self::FILTER_HTML, $translations['English']['content_html']);
        static::assertSame(self::FILTER_PLAIN, $translations['Deutsch']['content_plain']);
    }

    private function givenTheStoredTemplateUsesTheFilter(bool $editedByMerchant): void
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
             SET `translation`.`updated_at` = :updatedAt,
                 `translation`.`content_html` = :contentHtml,
                 `translation`.`content_plain` = :contentPlain
             WHERE `type`.`technical_name` = :technicalName',
            [
                'updatedAt' => $editedByMerchant ? '2026-01-01 00:00:00.000' : null,
                'contentHtml' => self::FILTER_HTML,
                'contentPlain' => self::FILTER_PLAIN,
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
            ['names' => ArrayParameterType::STRING]
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
