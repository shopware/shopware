<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Mail\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\MailFactory;
use Shopware\Core\Content\Mail\Service\MailSender;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
class EmailSenderTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    public function testSendEmail(): void
    {
        try {
            $this->doRunTest();
        } finally {
            // test updates container state, reset everything.
            KernelLifecycleManager::ensureKernelShutdown();
        }
    }

    private function doRunTest(): void
    {
        // other tests might have already booted the kernel...
        KernelLifecycleManager::ensureKernelShutdown();
        $container = static::getContainer();
        $transport = $this->createMock(TransportInterface::class);
        $container->set('mailer.transports', $transport);
        $mailFactory = $container->get(MailFactory::class);
        static::assertInstanceOf(MailFactory::class, $mailFactory);
        $filesystem = $container->get('shopware.filesystem.private');
        static::assertInstanceOf(FilesystemOperator::class, $filesystem);

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

        $mailSender = $container->get(MailSender::class);
        $serializedMail = serialize($mail);
        $expectedMailPath = 'mail-data/' . Hasher::hash($serializedMail);
        $transport->expects(static::once())
            ->method('send')
            ->with(
                static::callback(
                    fn (Email $email) => $email->getSubject() === $mail->getSubject() && $email->getHtmlBody() === $mail->getHtmlBody()
                )
            );

        $mailSender->send($mail);
        static::assertSame($serializedMail, $filesystem->read($expectedMailPath));

        $this->runWorker();
        static::assertFalse($filesystem->fileExists($expectedMailPath));
    }
}
