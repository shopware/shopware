<?php declare(strict_types=1);

namespace Shopware\Tests\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Traits\UpdateMailTrait;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
trait MailTemplateMigrationTrait
{
    use UpdateMailTrait;

    private const LANGUAGE_NAME_EN = 'English';
    private const LANGUAGE_NAME_DE = 'Deutsch';

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

        \assert($expected->getEnPlain() === $current->getEnPlain(), new \AssertionError('Expect "enPlain" to be same'));
        \assert($expected->getEnHtml() === $current->getEnHtml(), new \AssertionError('Expect "enHtml" to be same'));
        \assert($expected->getDePlain() === $current->getDePlain(), new \AssertionError('Expect "dePlain" to be same'));
        \assert($expected->getDeHtml() === $current->getDeHtml(), new \AssertionError('Expect "deHtml" to be same'));
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

    public function getTranslations(string $mailTemplateId): Translations
    {
        $languages = $this->getConnection()->fetchAllKeyValue('SELECT `name`, `id` FROM `language` WHERE `name` IN ("Deutsch", "English")');

        $translationArray = $this->getConnection()->fetchAllAssociativeIndexed(
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

    private function getConnection(): Connection
    {
        return KernelLifecycleManager::getConnection();
    }

    private function getMailTemplateTypeId(string $mailTemplateTypeTechnicalName): string
    {
        $result = $this->getConnection()->fetchOne(
            'SELECT `id` FROM `mail_template_type` WHERE `technical_name` = :technicalName',
            ['technicalName' => $mailTemplateTypeTechnicalName]
        );

        if (!$result) {
            throw new \RuntimeException('Coud not find mail template type id. Check the given technical_name.');
        }

        return $result;
    }

    private function getMailTemplateId(string $mailTemplateTypeId): string
    {
        $result = $this->getConnection()->fetchOne(
            'SELECT `id` FROM `mail_template` WHERE `mail_template_type_id` = :mailTemplateTypeId AND system_default = 1',
            ['mailTemplateTypeId' => $mailTemplateTypeId]
        );

        if (!$result) {
            throw new \RuntimeException('Coud not find mail template id');
        }

        return $result;
    }
}
