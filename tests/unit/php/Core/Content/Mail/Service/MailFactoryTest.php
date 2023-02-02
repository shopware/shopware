<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Validation\HappyPathValidator;
use Shopware\Core\Test\Annotation\ActiveFeatures;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 * @covers \Shopware\Core\Content\Mail\Service\MailFactory
 */
class MailFactoryTest extends TestCase
{
    public function testCreateWithoutFeatureFlag(): void
    {
        $this->mailFactoryCreateTestHelper();
    }

    /**
     * @ActiveFeatures("FEATURE_NEXT_16710", "V6_5_0_0")
     */
    public function testCreateWithFeatureFlag(): void
    {
        $this->mailFactoryCreateTestHelper();
    }

    private function mailFactoryCreateTestHelper(): void
    {
        $validatorMock = $this->createMock(HappyPathValidator::class);

        $attachmentPath = 'path';
        $tempFS = new Filesystem(new MemoryAdapter());
        $tempFS->write($attachmentPath, 'file content');

        $mailFactory = new MailFactory($validatorMock, $tempFS);
        $validatorMock->method('validate')->willReturn(new ConstraintViolationList());

        $subject = 'mail create test';
        $sender = ['testSender@example.org' => 'Sales Channel'];
        $recipients = ['testReceiver@example.org' => 'Receiver name'];
        $contents = ['text/html' => 'Message'];
        $attachments = [$attachmentPath];

        $additionalData = ['recipientsCc' => 'ccMailRecipient@example.com'];
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

        static::assertSame('Sales Channel', $mail->getFrom()[0]->getName());
        static::assertSame('testSender@example.org', $mail->getFrom()[0]->getAddress());

        static::assertSame('Receiver name', $mail->getTo()[0]->getName());
        static::assertSame('testReceiver@example.org', $mail->getTo()[0]->getAddress());

        static::assertSame('Message', $mail->getHtmlBody());
        static::assertEmpty($mail->getTextBody());

        $attach = Feature::isActive('v6.5.0.0') ? 'attachment' : 'inline';

        static::assertStringContainsString($attach, $mail->getAttachments()[0]->asDebugString());

        static::assertCount(2, $mail->getAttachments());

        static::assertSame('ccMailRecipient@example.com', $mail->getCc()[0]->getAddress());

        $tempFS->delete($attachmentPath);
    }
}
