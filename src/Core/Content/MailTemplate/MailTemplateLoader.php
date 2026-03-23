<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate;

use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Finder\Finder;

#[Package('after-sales')]
class MailTemplateLoader
{
    /**
     * Load mail templates from a directory on the local filesystem.
     *
     * Expects the following structure inside $basePath:
     *   mail-templates.xml
     *   {technical-name}/{locale}/html.twig
     *   {technical-name}/{locale}/plain.twig
     */
    public static function load(string $basePath): MailTemplates
    {
        $mailTemplates = MailTemplateXmlLoader::load($basePath . '/mail-templates.xml');

        foreach ($mailTemplates->getMailTemplates() as $mailTemplate) {
            $templateDir = $basePath . '/' . $mailTemplate->getTechnicalName();

            if (!is_dir($templateDir)) {
                continue;
            }

            self::loadTemplateContent($mailTemplate, $templateDir);
        }

        return $mailTemplates;
    }

    /**
     * Load mail templates using the app filesystem abstraction.
     */
    public static function loadFromFilesystem(Filesystem $filesystem, string $relativePath = 'Resources/mail-templates'): MailTemplates
    {
        $mailTemplates = MailTemplateXmlLoader::load(
            $filesystem->path($relativePath, 'mail-templates.xml')
        );

        foreach ($mailTemplates->getMailTemplates() as $mailTemplate) {
            $templateDir = $filesystem->path($relativePath, $mailTemplate->getTechnicalName());

            if (!is_dir($templateDir)) {
                continue;
            }

            self::loadTemplateContent($mailTemplate, $templateDir);
        }

        return $mailTemplates;
    }

    private static function loadTemplateContent(Xml\MailTemplate $mailTemplate, string $templateDir): void
    {
        $contentHtml = [];
        $contentPlain = [];

        $finder = new Finder();
        $finder->directories()->in($templateDir)->depth(0);

        foreach ($finder as $localeDir) {
            $locale = $localeDir->getFilename();

            $htmlFile = $localeDir->getPathname() . '/html.twig';
            if (is_file($htmlFile)) {
                $contentHtml[$locale] = file_get_contents($htmlFile) ?: '';
            }

            $plainFile = $localeDir->getPathname() . '/plain.twig';
            if (is_file($plainFile)) {
                $contentPlain[$locale] = file_get_contents($plainFile) ?: '';
            }
        }

        $mailTemplate->setContentHtml($contentHtml);
        $mailTemplate->setContentPlain($contentPlain);
    }
}
