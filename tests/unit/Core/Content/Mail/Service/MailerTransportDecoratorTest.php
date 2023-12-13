<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Service;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Adapter\Filesystem\MemoryFilesystemAdapter;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[CoversClass(MailerTransportDecorator::class)]
class MailerTransportDecoratorTest extends TestCase
{
    private MockObject&TransportInterface $decorated;

    private MockObject&MailAttachmentsBuilder $attachmentsBuilder;

    private Filesystem $filesystem;

    private MockObject&EntityRepository $documentRepository;

    private MailerTransportDecorator $decorator;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(TransportInterface::class);
        $this->attachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $this->filesystem = new Filesystem(new MemoryFilesystemAdapter());
        $this->documentRepository = $this->createMock(EntityRepository::class);

        $this->decorator = new MailerTransportDecorator(
            $this->decorated,
            $this->attachmentsBuilder,
            $this->filesystem,
            $this->documentRepository
        );
    }

    public function testMailerTransportDecoratorDefault(): void
    {
        $mail = $this->createMock(Email::class);
        $envelope = $this->createMock(Envelope::class);

        $this->decorated->expects(static::once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
    }

    public function testMailerTransportDecoratorWithUrlAttachments(): void
    {
        $mail = new Mail();
        $envelope = $this->createMock(Envelope::class);
        $mail->addAttachmentUrl('foo');
        $mail->addAttachmentUrl('bar');

        $this->filesystem->write('foo', 'foo');
        $this->filesystem->write('bar', 'bar');

        $this->decorated->expects(static::once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());
    }

    public function testMailerTransportDecoratorWithBuildAttachments(): void
    {
        $mail = new Mail();
        $envelope = $this->createMock(Envelope::class);
        $mailAttachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false, ['foo', 'bar']),
            [],
            Uuid::randomHex()
        );

        $mail->setMailAttachmentsConfig($mailAttachmentsConfig);

        $this->decorated->expects(static::once())->method('send')->with($mail, $envelope);

        $this->attachmentsBuilder
            ->expects(static::once())
            ->method('buildAttachments')
            ->with(
                $mailAttachmentsConfig->getContext(),
                $mailAttachmentsConfig->getMailTemplate(),
                $mailAttachmentsConfig->getExtension(),
                $mailAttachmentsConfig->getEventConfig(),
                $mailAttachmentsConfig->getOrderId()
            )
            ->willReturn([
                ['id' => 'foo', 'content' => 'foo', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
                ['id' => 'bar', 'content' => 'bar', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
            ]);

        $this->documentRepository
            ->expects(static::once())
            ->method('update')
            ->with([
                ['id' => 'foo', 'sent' => true],
                ['id' => 'bar', 'sent' => true],
            ], Context::createDefaultContext());

        $this->decorator->send($mail, $envelope);

        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());
    }
}
