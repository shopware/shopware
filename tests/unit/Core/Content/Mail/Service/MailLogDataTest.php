<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailLogData;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailLogData::class)]
class MailLogDataTest extends TestCase
{
    public function testOnlyMetadataIsLogged(): void
    {
        $data = [
            'subject' => 'Password recovery',
            'recipients' => ['customer@example.com' => 'Customer'],
            'templateId' => 'template-id',
            'salesChannelId' => 'sales-channel-id',
            'eventName' => 'customer.recovery.request',
        ];

        static::assertSame([
            ...$data,
            'exceptionClass' => \RuntimeException::class,
            'exceptionCode' => 42,
        ], MailLogData::create([
            ...$data,
            'contentHtml' => 'BODY-SECRET',
            'contentPlain' => 'BODY-SECRET',
            'templateData' => ['resetUrl' => 'BODY-SECRET'],
            'attachments' => ['BODY-SECRET'],
            'unknownExtensionData' => 'BODY-SECRET',
        ], new \RuntimeException('BODY-SECRET', 42)));
    }

    public function testExtractsHeadersWithoutReadingBodiesOrAttachments(): void
    {
        $email = (new Email())
            ->subject('Password recovery')
            ->to('customer@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->html('BODY-SECRET')
            ->text('BODY-SECRET')
            ->attach('BODY-SECRET', 'attachment.txt');
        $email->getHeaders()->addIdHeader('Message-ID', 'delivery@example.com');

        $expected = [
            'subject' => 'Password recovery',
            'recipients' => ['customer@example.com' => ''],
            'recipientsCc' => ['cc@example.com' => ''],
            'recipientsBcc' => ['bcc@example.com' => ''],
            'messageId' => ['delivery@example.com'],
        ];

        static::assertSame($expected, MailLogData::fromMessage($email));
        // Signing can replace Email with a MIME Message, which still has structured headers.
        static::assertSame($expected, MailLogData::fromMessage(new Message($email->getHeaders(), $email->getBody())));
    }

    public function testRawMessagesAreNotSerializedForLogging(): void
    {
        $message = $this->createMock(RawMessage::class);
        $message->expects($this->never())->method('toString');

        static::assertSame([], MailLogData::fromMessage($message));
    }
}
