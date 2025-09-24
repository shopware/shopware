<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class MailTemplateMigrationTestCase extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use UpdateMailTrait;

    public const LANGUAGE_NAME_EN = 'English';
    public const LANGUAGE_NAME_DE = 'Deutsch';

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public static function assertMailTemplateTranslations(Translations $expected, Translations $current): void
    {
        $fileSystem = new Filesystem();
        if ($fileSystem->exists((string) $expected->getEnPlain())) {
            $expected->setEnPlain($fileSystem->readFile((string) $expected->getEnPlain()));
        }

        if ($fileSystem->exists((string) $expected->getEnHtml())) {
            $expected->setEnHtml($fileSystem->readFile((string) $expected->getEnHtml()));
        }

        if ($fileSystem->exists((string) $expected->getDePlain())) {
            $expected->setDePlain($fileSystem->readFile((string) $expected->getDePlain()));
        }

        if ($fileSystem->exists((string) $expected->getDeHtml())) {
            $expected->setDeHtml($fileSystem->readFile((string) $expected->getDeHtml()));
        }

        static::assertSame($expected->getEnPlain(), $current->getEnPlain());
        static::assertSame($expected->getEnHtml(), $current->getEnHtml());
        static::assertSame($expected->getDePlain(), $current->getDePlain());
        static::assertSame($expected->getDeHtml(), $current->getDeHtml());
    }

    public function getMailTemplateTranslations(string $mailTemplateTypeTechnicalName): MailTemplateTranslationResult
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($mailTemplateTypeTechnicalName);
        $mailTemplateId = $this->getMailTemplateId($mailTemplateTypeId);

        $translations = $this->getTranslations($mailTemplateId);

        return new MailTemplateTranslationResult(
            $mailTemplateTypeTechnicalName,
            $mailTemplateTypeId,
            $mailTemplateId,
            $translations
        );
    }

    protected function getTranslations(string $mailTemplateId): Translations
    {
        $languages = $this->connection->fetchAllKeyValue('SELECT `name`, `id` FROM `language` WHERE `name` IN ("Deutsch", "English")');

        $translationArray = $this->connection->fetchAllAssociativeIndexed(
            'SELECT `language_id`, `content_html`, `content_plain`  FROM `mail_template_translation` WHERE `mail_template_id` = :mailTemplateId',
            [
                'mailTemplateId' => $mailTemplateId,
            ]
        );

        $translations = new Translations();
        foreach ($languages as $languageName => $languageId) {
            if ($languageName === self::LANGUAGE_NAME_EN) {
                $translations->setEnPlain($translationArray[$languageId]['content_plain']);
                $translations->setEnHtml($translationArray[$languageId]['content_html']);
            }

            if ($languageName === self::LANGUAGE_NAME_DE) {
                $translations->setDePlain($translationArray[$languageId]['content_plain']);
                $translations->setDeHtml($translationArray[$languageId]['content_html']);
            }
        }

        return $translations;
    }

    protected function getMailTemplateTypeId(string $mailTemplateTypeTechnicalName): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $mailTemplateTypeTechnicalName]
        );

        if (!$result) {
            static::fail('Could not find mail template type id. Check the given technical_name.');
        }

        return $result;
    }

    protected function getMailTemplateId(string $mailTemplateTypeId): string
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$result) {
            static::fail('Could not find mail template id');
        }

        return $result;
    }
}
