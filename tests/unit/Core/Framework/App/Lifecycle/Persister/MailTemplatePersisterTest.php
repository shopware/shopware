<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateSetPersister;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\MailTemplatePersister;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[CoversClass(MailTemplatePersister::class)]
class MailTemplatePersisterTest extends TestCase
{
    public function testPersistWithMailTemplatesXml(): void
    {
        $sfFilesystem = new SymfonyFilesystem();
        $tempDir = sys_get_temp_dir() . '/shopware_mail_persister_test_' . bin2hex(random_bytes(8));
        $sfFilesystem->mkdir($tempDir . '/Resources/mail-templates/test_mail/en-GB', 0777);

        try {
            $sfFilesystem->dumpFile($tempDir . '/Resources/mail-templates/mail-templates.xml', '<?xml version="1.0" encoding="utf-8"?>
<mail-templates xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Content/MailTemplate/Schema/mail-templates-1.0.xsd">
    <mail-template technical-name="test_mail">
        <name>Test mail</name>
        <subject>Test subject</subject>
    </mail-template>
</mail-templates>');

            $sfFilesystem->dumpFile($tempDir . '/Resources/mail-templates/test_mail/en-GB/html.twig', '<p>Test</p>');
            $sfFilesystem->dumpFile($tempDir . '/Resources/mail-templates/test_mail/en-GB/plain.twig', 'Test');

            $persister = $this->createMock(MailTemplateSetPersister::class);
            $persister->expects($this->once())
                ->method('sync')
                ->with(
                    static::callback(function (MailTemplates $mailTemplates): bool {
                        $templates = $mailTemplates->getMailTemplates();
                        if (\count($templates) !== 1) {
                            return false;
                        }

                        $template = $templates[0];

                        return $template->getTechnicalName() === 'test_mail'
                            && $template->getContentHtml() === ['en-GB' => '<p>Test</p>']
                            && $template->getContentPlain() === ['en-GB' => 'Test'];
                    }),
                    static::isInstanceOf(Context::class)
                );

            $mailTemplatePersister = new MailTemplatePersister($persister);

            $app = new AppEntity();
            $app->setId('test-app-id');

            $context = new AppLifecycleContext(
                Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/manifest.xml'),
                $app,
                Context::createDefaultContext(),
                new Filesystem($tempDir),
                'en-GB',
                true,
            );

            $mailTemplatePersister->persist($context);
        } finally {
            $sfFilesystem->remove($tempDir);
        }
    }

    public function testPersistWithNoMailTemplatesDoesNothing(): void
    {
        $sfFilesystem = new SymfonyFilesystem();
        $tempDir = sys_get_temp_dir() . '/shopware_mail_persister_test_' . bin2hex(random_bytes(8));
        $sfFilesystem->mkdir($tempDir);

        try {
            $persister = $this->createMock(MailTemplateSetPersister::class);
            $persister->expects($this->never())->method('sync');

            $mailTemplatePersister = new MailTemplatePersister($persister);

            $app = new AppEntity();
            $app->setId('test-app-id');

            $context = new AppLifecycleContext(
                Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/manifest.xml'),
                $app,
                Context::createDefaultContext(),
                new Filesystem($tempDir),
                'en-GB',
                true,
            );

            $mailTemplatePersister->persist($context);
        } finally {
            $sfFilesystem->remove($tempDir);
        }
    }
}
