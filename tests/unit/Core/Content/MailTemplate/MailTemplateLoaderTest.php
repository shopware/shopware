<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateLoader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(MailTemplateLoader::class)]
class MailTemplateLoaderTest extends TestCase
{
    public function testLoadCombinesXmlWithTwigContent(): void
    {
        $mailTemplates = MailTemplateLoader::load(__DIR__ . '/_fixtures');

        static::assertCount(1, $mailTemplates->getMailTemplates());

        $mailTemplate = $mailTemplates->getMailTemplates()[0];

        static::assertSame('order_confirmation', $mailTemplate->getTechnicalName());

        // Content should be loaded from twig files
        $contentHtml = $mailTemplate->getContentHtml();
        static::assertArrayHasKey('en-GB', $contentHtml);
        static::assertArrayHasKey('de-DE', $contentHtml);
        static::assertStringContainsString('Order confirmation', $contentHtml['en-GB']);
        static::assertStringContainsString('Bestellbestätigung', $contentHtml['de-DE']);

        $contentPlain = $mailTemplate->getContentPlain();
        static::assertArrayHasKey('en-GB', $contentPlain);
        static::assertArrayHasKey('de-DE', $contentPlain);
        static::assertStringContainsString('Thank you for your order', $contentPlain['en-GB']);
        static::assertStringContainsString('Vielen Dank', $contentPlain['de-DE']);
    }

    public function testLoadWithMissingTemplateDirSkipsContent(): void
    {
        $filesystem = new Filesystem();
        $tempDir = sys_get_temp_dir() . '/shopware_mail_template_test_' . bin2hex(random_bytes(8));
        $filesystem->mkdir($tempDir);

        try {
            $filesystem->copy(
                __DIR__ . '/_fixtures/mail-templates.xml',
                $tempDir . '/mail-templates.xml'
            );

            // No template directory exists — content should be empty
            $mailTemplates = MailTemplateLoader::load($tempDir);
            $mailTemplate = $mailTemplates->getMailTemplates()[0];

            static::assertSame([], $mailTemplate->getContentHtml());
            static::assertSame([], $mailTemplate->getContentPlain());
        } finally {
            $filesystem->remove($tempDir);
        }
    }

    public function testLoadWithPartialLocalesOnlyLoadsExisting(): void
    {
        $filesystem = new Filesystem();
        $tempDir = sys_get_temp_dir() . '/shopware_mail_template_test_' . bin2hex(random_bytes(8));
        $filesystem->mkdir($tempDir . '/order_confirmation/en-GB', 0777);

        try {
            $filesystem->copy(
                __DIR__ . '/_fixtures/mail-templates.xml',
                $tempDir . '/mail-templates.xml'
            );

            // Only en-GB with html.twig
            $filesystem->dumpFile(
                $tempDir . '/order_confirmation/en-GB/html.twig',
                '<p>Test</p>'
            );

            $mailTemplates = MailTemplateLoader::load($tempDir);
            $mailTemplate = $mailTemplates->getMailTemplates()[0];

            static::assertSame(['en-GB' => '<p>Test</p>'], $mailTemplate->getContentHtml());
            static::assertSame([], $mailTemplate->getContentPlain());
        } finally {
            $filesystem->remove($tempDir);
        }
    }
}
