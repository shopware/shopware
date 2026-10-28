<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailLogData;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailErrorEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;
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
    #[DataProvider('mailTypes')]
    public function testMailEventLoggingPolicy(string $eventName, bool $explicitlySensitive, bool $expectedSensitive): void
    {
        $data = [
            'subject' => 'Subject',
            'contentHtml' => 'BODY-SECRET',
            'contentPlain' => 'BODY-SECRET',
            'sensitive' => $explicitlySensitive,
        ];
        $templateData = ['eventName' => $eventName, 'resetUrl' => 'BODY-SECRET'];
        $context = Context::createDefaultContext();
        $mail = (new Email())->subject('Subject')->text('BODY-SECRET')->html('BODY-SECRET');
        $events = [
            new MailBeforeValidateEvent($data, $context, $templateData),
            new MailBeforeSentEvent($data, $mail, $context, $eventName),
            new MailSentEvent('Subject', [], ['text/plain' => 'BODY-SECRET'], $context, $eventName, $data, $templateData, $mail),
            new MailErrorEvent($context, throwable: new \RuntimeException('BODY-SECRET'), template: 'BODY-SECRET', templateData: $templateData, data: $data, mail: $mail),
        ];

        foreach ($events as $event) {
            $log = json_encode($event->getLogData(), \JSON_THROW_ON_ERROR);
            static::assertSame(!$expectedSensitive, str_contains($log, 'BODY-SECRET'), $event::class);
        }
    }

    /**
     * @return \Generator<string, array{string, bool, bool}>
     */
    public static function mailTypes(): \Generator
    {
        yield 'admin recovery is sensitive' => ['user.recovery.request', false, true];
        yield 'customer recovery is sensitive' => ['customer.recovery.request', false, true];
        yield 'explicit sensitivity applies to other events' => ['checkout.order.placed', true, true];
        yield 'ordinary mail keeps content' => ['checkout.order.placed', false, false];
    }

    public function testSensitiveMarkerSurvivesQueueSerializationAndSigning(): void
    {
        $mail = (new Mail())->subject('Recovery')->text('BODY-SECRET');
        $mail->getHeaders()->addTextHeader(MailLogData::SENSITIVE_HEADER, '1');
        $restored = Serialization::assertRoundTrip($mail);

        static::assertInstanceOf(Email::class, $restored);
        static::assertTrue(MailLogData::isSensitiveMessage($restored));
        static::assertTrue(MailLogData::isSensitiveMessage(new Message($restored->getHeaders(), $restored->getBody())));
        static::assertFalse(MailLogData::isSensitiveMessage(new Email()));
        static::assertTrue(MailLogData::isSensitiveMessage(new RawMessage('BODY-SECRET')));
    }

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
