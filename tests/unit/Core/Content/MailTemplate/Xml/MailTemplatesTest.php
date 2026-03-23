<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateXmlLoader;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplates;

/**
 * @internal
 */
#[CoversClass(MailTemplates::class)]
class MailTemplatesTest extends TestCase
{
    public function testFromXml(): void
    {
        $mailTemplates = MailTemplateXmlLoader::load(__DIR__ . '/../_fixtures/mail-templates.xml');

        static::assertCount(1, $mailTemplates->getMailTemplates());
    }

    public function testFromArrayEmpty(): void
    {
        $mailTemplates = MailTemplates::fromArray([]);

        static::assertSame([], $mailTemplates->getMailTemplates());
    }
}
