<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\MailTemplateXmlLoader;

/**
 * @internal
 */
#[CoversClass(MailTemplateXmlLoader::class)]
class MailTemplateXmlLoaderTest extends TestCase
{
    public function testLoadValidXml(): void
    {
        $mailTemplates = MailTemplateXmlLoader::load(__DIR__ . '/_fixtures/mail-templates.xml');

        static::assertCount(1, $mailTemplates->getMailTemplates());

        $mailTemplate = $mailTemplates->getMailTemplates()[0];

        static::assertSame('order_confirmation', $mailTemplate->getTechnicalName());
        static::assertSame([
            'en-GB' => 'Order confirmation',
            'de-DE' => 'Bestellbestätigung',
        ], $mailTemplate->getName());
        static::assertSame([
            'en-GB' => 'Your order {{ order.orderNumber }}',
            'de-DE' => 'Ihre Bestellung {{ order.orderNumber }}',
        ], $mailTemplate->getSubject());
        static::assertSame([
            'en-GB' => '{{ salesChannel.name }}',
            'de-DE' => '{{ salesChannel.name }}',
        ], $mailTemplate->getSenderName());
        static::assertSame([
            'en-GB' => 'Sent after order placement',
            'de-DE' => 'Wird nach Bestellaufgabe versendet',
        ], $mailTemplate->getDescription());
        static::assertSame([
            'order' => 'order',
            'salesChannel' => 'salesChannel',
        ], $mailTemplate->getAvailableEntities());
    }

    public function testLoadInvalidXmlThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MailTemplateXmlLoader::load(__DIR__ . '/_fixtures/order_confirmation/en-GB/html.twig');
    }
}
