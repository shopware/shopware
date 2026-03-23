<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateXmlLoader;
use Shopware\Core\Content\MailTemplate\Xml\MailTemplate;

/**
 * @internal
 */
#[CoversClass(MailTemplate::class)]
class MailTemplateTest extends TestCase
{
    public function testParsing(): void
    {
        $mailTemplates = MailTemplateXmlLoader::load(__DIR__ . '/../_fixtures/mail-templates.xml');
        $mailTemplate = $mailTemplates->getMailTemplates()[0];

        static::assertSame('order_confirmation', $mailTemplate->getTechnicalName());
        static::assertSame('Order confirmation', $mailTemplate->getName()['en-GB']);
        static::assertSame('Bestellbestätigung', $mailTemplate->getName()['de-DE']);
        static::assertSame('Your order {{ order.orderNumber }}', $mailTemplate->getSubject()['en-GB']);
        static::assertSame('{{ salesChannel.name }}', $mailTemplate->getSenderName()['en-GB']);
        static::assertSame('Sent after order placement', $mailTemplate->getDescription()['en-GB']);
        static::assertSame(['order' => 'order', 'salesChannel' => 'salesChannel'], $mailTemplate->getAvailableEntities());
    }

    public function testContentSetters(): void
    {
        $mailTemplates = MailTemplateXmlLoader::load(__DIR__ . '/../_fixtures/mail-templates.xml');
        $mailTemplate = $mailTemplates->getMailTemplates()[0];

        static::assertSame([], $mailTemplate->getContentHtml());
        static::assertSame([], $mailTemplate->getContentPlain());

        $mailTemplate->setContentHtml(['en-GB' => '<p>Hello</p>']);
        $mailTemplate->setContentPlain(['en-GB' => 'Hello']);

        static::assertSame(['en-GB' => '<p>Hello</p>'], $mailTemplate->getContentHtml());
        static::assertSame(['en-GB' => 'Hello'], $mailTemplate->getContentPlain());
    }
}
