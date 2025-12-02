<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Administration\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Administration\Migration\V6_7\Migration1757057005MailTemplate;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\Traits\MailUpdate;
use Shopware\Tests\Migration\MailTemplateMigrationTestCase;
use Shopware\Tests\Migration\Translations;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1757057005MailTemplate::class)]
class Migration1757057005MailTemplateTest extends MailTemplateMigrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1757057005MailTemplate();
        static::assertSame(1757057005, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        // prepare the test
        $expectedTranslations = new Translations();
        $expectedTranslations->setEnPlain('en plain text');
        $expectedTranslations->setEnHtml('<h1>en HTML</h1>');
        $expectedTranslations->setDePlain('de plain text');
        $expectedTranslations->setDeHtml('<h1>de HTML</h1>');

        $mailTranslations = new MailUpdate(
            'admin_sso_user_invite',
            $expectedTranslations->getEnPlain(),
            $expectedTranslations->getEnHtml(),
            $expectedTranslations->getDePlain(),
            $expectedTranslations->getDeHtml(),
        );

        $this->updateMail($mailTranslations, $this->connection);
        $currentTranslations = $this->getMailTemplateTranslations($mailTranslations->getType());

        static::assertMailTemplateTranslations($expectedTranslations, $currentTranslations->translations);

        // Start with the test
        $migration = new Migration1757057005MailTemplate();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $dir = realpath(__DIR__ . '/../../../../src/Administration/Migration/V6_7/assets');
        $expectedTranslations = new Translations();
        $expectedTranslations->setEnPlain($dir . '/sso_user_invitation_mail.en-GB.txt');
        $expectedTranslations->setEnHtml($dir . '/sso_user_invitation_mail.en-GB.html.twig');
        $expectedTranslations->setDePlain($dir . '/sso_user_invitation_mail.de-DE.txt');
        $expectedTranslations->setDeHtml($dir . '/sso_user_invitation_mail.de-DE.html.twig');

        $currentTranslations = $this->getMailTemplateTranslations($mailTranslations->getType());

        static::assertMailTemplateTranslations($expectedTranslations, $currentTranslations->translations);
    }
}
