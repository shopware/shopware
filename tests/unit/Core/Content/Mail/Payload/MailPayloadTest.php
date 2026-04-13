<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Payload;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Payload\MailPayload;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailPayload::class)]
class MailPayloadTest extends TestCase
{
    public function testToArrayWithMinimalPayload(): void
    {
        $payload = new MailPayload(
            recipients: ['recipient@example.com' => 'Recipient'],
            contentHtml: '<p>Hello</p>',
            contentPlain: 'Hello',
            subject: 'Subject',
            senderName: 'Sender',
        );

        static::assertSame(
            [
                'recipients' => ['recipient@example.com' => 'Recipient'],
                'contentHtml' => '<p>Hello</p>',
                'contentPlain' => 'Hello',
                'subject' => 'Subject',
                'senderName' => 'Sender',
                'testMode' => false,
                'documentIds' => [],
                'mediaIds' => [],
                'attachments' => [],
            ],
            $payload->toArray()
        );
    }

    public function testToArrayIncludesAllOptionalFieldsWhenPresent(): void
    {
        $binAttachments = [['content' => 'binary', 'fileName' => 'test.txt', 'mimeType' => 'text/plain']];

        $payload = new MailPayload(
            recipients: ['recipient@example.com' => null],
            contentHtml: '<p>Hello</p>',
            contentPlain: 'Hello',
            subject: 'Subject',
            senderName: 'Sender',
            senderMail: 'sender-mail@example.com',
            senderEmail: 'sender-email@example.com',
            salesChannelId: 'sales-channel-id',
            documentIds: ['document-id'],
            mediaIds: ['media-id'],
            attachments: ['attachment-url'],
            binAttachments: $binAttachments,
            testMode: true,
            recipientsCc: 'cc@example.com',
            recipientsBcc: ['bcc@example.com' => 'BCC'],
            replyTo: ['reply@example.com' => 'Reply'],
            returnPath: 'return@example.com',
        );

        static::assertSame(
            [
                'recipients' => ['recipient@example.com' => null],
                'contentHtml' => '<p>Hello</p>',
                'contentPlain' => 'Hello',
                'subject' => 'Subject',
                'senderName' => 'Sender',
                'testMode' => true,
                'documentIds' => ['document-id'],
                'mediaIds' => ['media-id'],
                'attachments' => ['attachment-url'],
                'senderMail' => 'sender-mail@example.com',
                'senderEmail' => 'sender-email@example.com',
                'salesChannelId' => 'sales-channel-id',
                'binAttachments' => $binAttachments,
                'recipientsCc' => 'cc@example.com',
                'recipientsBcc' => ['bcc@example.com' => 'BCC'],
                'replyTo' => ['reply@example.com' => 'Reply'],
                'returnPath' => 'return@example.com',
            ],
            $payload->toArray()
        );
    }
}
