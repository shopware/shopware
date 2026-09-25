<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\Product\Garan\GaranLabelInlineImage;
use Shopware\Core\Content\Product\Garan\GaranLabelMailSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelMailSubscriber::class)]
class GaranLabelMailSubscriberTest extends TestCase
{
    public function testSubscribesToMailBeforeSentEvent(): void
    {
        static::assertSame(
            [MailBeforeSentEvent::class => 'embedLabelImages'],
            GaranLabelMailSubscriber::getSubscribedEvents()
        );
    }

    public function testEmbedsReferencedLabelAsInlineImage(): void
    {
        $email = $this->createEmail('<p>Product</p><img src="cid:garan-label-nested-36.png" alt="GARAN">');

        $this->dispatch($email);

        $attachments = $email->getAttachments();
        static::assertCount(1, $attachments);

        $part = $attachments[0];
        static::assertSame('garan-label-nested-36.png', $part->getName());
        static::assertSame('image/png', $part->getMediaType() . '/' . $part->getMediaSubtype());

        $html = $email->getBody()->bodyToString();
        static::assertStringContainsString('Content-Disposition: inline', $html);
        static::assertStringContainsString('cid:' . $part->getContentId(), $html, 'Symfony Mime has to link the reference to the attached part');
        static::assertStringNotContainsString('cid:garan-label-nested-36.png', $html);
    }

    public function testEmbedsEachLabelOnlyOnce(): void
    {
        $email = $this->createEmail(
            '<img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-30.png">'
        );

        $this->dispatch($email);
        $this->dispatch($email);

        static::assertSame(
            ['garan-label-nested-36.png', 'garan-label-nested-30.png'],
            array_map(static fn (DataPart $part): ?string => $part->getName(), $email->getAttachments())
        );
    }

    public function testIgnoresReferencesWithoutPreRenderedImage(): void
    {
        $email = $this->createEmail('<img src="cid:garan-label-nested-999.png"><img src="cid:logo.png">');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    public function testIgnoresMailsWithoutHtmlBody(): void
    {
        $email = (new Email())->text('cid:garan-label-nested-36.png');

        $this->dispatch($email);

        static::assertSame([], $email->getAttachments());
    }

    private function createEmail(string $html): Email
    {
        return (new Email())
            ->from('shop@example.com')
            ->to('customer@example.com')
            ->subject('Order confirmation')
            ->text('Order confirmation')
            ->html($html);
    }

    private function dispatch(Email $email): void
    {
        (new GaranLabelMailSubscriber(new GaranLabelInlineImage()))
            ->embedLabelImages(new MailBeforeSentEvent([], $email, Context::createDefaultContext()));
    }
}
