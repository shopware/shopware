<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Payload\MailPayloadFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailPayloadFactory::class)]
class MailPayloadFactoryTest extends TestCase
{
    public function testMakeNormalizesPayloadValues(): void
    {
        $factory = new MailPayloadFactory();

        $request = new RequestDataBag([
            'recipients' => [
                'recipient@example.com' => 'Recipient',
                1 => 'ignored',
                'null-name@example.com' => 123,
            ],
            'contentHtml' => '<p>Hello</p>',
            'contentPlain' => 'Hello',
            'subject' => 'Subject',
            'senderName' => 'Sender',
            'senderMail' => 'sender-mail@example.com',
            'senderEmail' => 'sender-email@example.com',
            'salesChannelId' => 'sales-channel-id',
            'documentIds' => ['document-id', 2],
            'mediaIds' => ['media-id', false],
            'attachments' => ['attachment-url'],
            'binAttachments' => [['content' => 'binary', 'fileName' => 'test.txt', 'mimeType' => 'text/plain']],
            'testMode' => '1',
            'recipientsCc' => ['cc@example.com' => 'CC', 2 => 'ignored'],
            'recipientsBcc' => 'bcc@example.com',
            'replyTo' => ['reply@example.com' => 5],
            'returnPath' => false,
        ]);

        $payload = $factory->make($request);

        static::assertSame(
            [
                'recipient@example.com' => 'Recipient',
                'null-name@example.com' => null,
            ],
            $payload->recipients
        );
        static::assertSame('<p>Hello</p>', $payload->contentHtml);
        static::assertSame('Hello', $payload->contentPlain);
        static::assertSame('Subject', $payload->subject);
        static::assertSame('Sender', $payload->senderName);
        static::assertSame('sender-mail@example.com', $payload->senderMail);
        static::assertSame('sender-email@example.com', $payload->senderEmail);
        static::assertSame('sales-channel-id', $payload->salesChannelId);
        static::assertSame(['document-id'], $payload->documentIds);
        static::assertSame(['media-id'], $payload->mediaIds);
        static::assertSame(['attachment-url'], $payload->attachments);
        static::assertSame([['content' => 'binary', 'fileName' => 'test.txt', 'mimeType' => 'text/plain']], $payload->binAttachments);
        static::assertTrue($payload->testMode);
        static::assertSame(['cc@example.com' => 'CC'], $payload->recipientsCc);
        static::assertSame('bcc@example.com', $payload->recipientsBcc);
        static::assertSame(['reply@example.com' => null], $payload->replyTo);
        static::assertNull($payload->returnPath);
        static::assertSame([], $payload->extensions);
    }

    public function testMakeUsesRequestValuesBeforeOverridesAndFallsBackToOverrides(): void
    {
        $factory = new MailPayloadFactory();

        $request = new RequestDataBag([
            'subject' => 'request subject',
            'recipients' => ['recipient@example.com' => 'Recipient'],
        ]);

        $payload = $factory->make($request, [
            'subject' => 'override subject',
            'senderName' => 'override sender',
            'contentHtml' => '<p>override html</p>',
            'documentIds' => ['document-id'],
            'recipientsCc' => 'cc@example.com',
        ]);

        static::assertSame('request subject', $payload->subject);
        static::assertSame('override sender', $payload->senderName);
        static::assertSame('<p>override html</p>', $payload->contentHtml);
        static::assertSame(['document-id'], $payload->documentIds);
        static::assertSame('cc@example.com', $payload->recipientsCc);
        static::assertSame([], $payload->extensions);
    }

    public function testMakeOnlyKeepsExplicitExtensionsByDefault(): void
    {
        $factory = new MailPayloadFactory();

        $request = new RequestDataBag([
            'recipients' => ['recipient@example.com' => 'Recipient'],
            'legacyTopLevelKey' => 'legacy value',
            'extensions' => [
                'explicitKey' => 'explicit value',
                1 => 'ignored',
            ],
        ]);

        $payload = $factory->make($request);

        static::assertSame(
            [
                'explicitKey' => 'explicit value',
            ],
            $payload->extensions
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testMakeCollectsExplicitAndLegacyExtensionsBeforeNextMajor(): void
    {
        $factory = new MailPayloadFactory();

        $request = new RequestDataBag([
            'recipients' => ['recipient@example.com' => 'Recipient'],
            'legacyTopLevelKey' => 'legacy value',
            'extensions' => [
                'explicitKey' => 'explicit value',
                1 => 'ignored',
            ],
        ]);

        $payload = $factory->make($request);

        static::assertSame(
            [
                'legacyTopLevelKey' => 'legacy value',
                'explicitKey' => 'explicit value',
            ],
            $payload->extensions
        );
    }
}
