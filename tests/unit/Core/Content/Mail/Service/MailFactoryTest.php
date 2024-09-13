<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Framework\Validation\HappyPathValidator;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(MailFactory::class)]
class MailFactoryTest extends TestCase
{
    public function testCreateWithFeatureFlag(): void
    {
        $validatorMock = $this->createMock(HappyPathValidator::class);

        $mailFactory = new MailFactory($validatorMock);
        $validatorMock->method('validate')->willReturn(new ConstraintViolationList());

        $subject = 'mail create test';
        $sender = ['testSender@example.org' => 'Sales Channel'];
        $recipients = ['testReceiver@example.org' => 'Receiver name', 'null-name@example.org' => null];
        $contents = ['text/html' => 'Message'];
        $attachments = ['test'];

        $additionalData = [
            'recipientsCc' => 'ccMailRecipient@example.com',
            'recipientsBcc' => [
                'bccMailRecipient1@example.com' => 'bccMailRecipient1',
                'bccMailRecipient2@example.com' => 'bccMailRecipient2',
            ],
        ];
        $binAttachments = [['content' => 'Content', 'fileName' => 'content.txt', 'mimeType' => 'application/txt']];

        $mail = $mailFactory->create(
            $subject,
            $sender,
            $recipients,
            $contents,
            $attachments,
            $additionalData,
            $binAttachments
        );

        static::assertInstanceOf(Mail::class, $mail);

        static::assertSame('Sales Channel', $mail->getFrom()[0]->getName());
        static::assertSame('testSender@example.org', $mail->getFrom()[0]->getAddress());

        static::assertSame('Receiver name', $mail->getTo()[0]->getName());
        static::assertSame('testReceiver@example.org', $mail->getTo()[0]->getAddress());

        static::assertSame('', $mail->getTo()[1]->getName());
        static::assertSame('null-name@example.org', $mail->getTo()[1]->getAddress());

        static::assertSame('Message', $mail->getHtmlBody());
        static::assertEmpty($mail->getTextBody());

        static::assertStringContainsString('attachment', $mail->getAttachments()[0]->asDebugString());

        static::assertCount(1, $mail->getAttachments());

        static::assertEquals($attachments, $mail->getAttachmentUrls());

        static::assertSame('ccMailRecipient@example.com', $mail->getCc()[0]->getAddress());

        static::assertSame('bccMailRecipient1', $mail->getBcc()[0]->getName());
        static::assertSame('bccMailRecipient1@example.com', $mail->getBcc()[0]->getAddress());
        static::assertSame('bccMailRecipient2', $mail->getBcc()[1]->getName());
        static::assertSame('bccMailRecipient2@example.com', $mail->getBcc()[1]->getAddress());
    }
}
