<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Mail\Transport;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Document\DocumentCollection;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\Mail\Transport\MailerTransportDecorator;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(MailerTransportDecorator::class)]
class MailerTransportDecoratorTest extends TestCase
{
    private MockObject&TransportInterface $decorated;

    private Stub&MailAttachmentsBuilder $attachmentsBuilder;

    private Filesystem $filesystem;

    /**
     * @var Stub&EntityRepository<DocumentCollection>
     */
    private Stub&EntityRepository $documentRepository;

    private MailerTransportDecorator $decorator;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(TransportInterface::class);
        $this->attachmentsBuilder = static::createStub(MailAttachmentsBuilder::class);
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->documentRepository = static::createStub(EntityRepository::class);

        $this->decorator = new MailerTransportDecorator(
            $this->decorated,
            $this->attachmentsBuilder,
            $this->filesystem,
            $this->documentRepository
        );
    }

    public function testMailerTransportDecoratorDefault(): void
    {
        $mail = static::createStub(Email::class);
        $envelope = static::createStub(Envelope::class);

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
    }

    public function testMailerTransportDecoratorWithUrlAttachments(): void
    {
        $mail = new Mail();
        $envelope = static::createStub(Envelope::class);
        $mail->addAttachmentUrl('foo');
        $mail->addAttachmentUrl('bar');

        $this->filesystem->write('foo', 'foo');
        $this->filesystem->write('bar', 'bar');

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $this->decorator->send($mail, $envelope);
        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());
    }

    public function testMailerTransportDecoratorWithBuildAttachments(): void
    {
        $mail = new Mail();
        $envelope = static::createStub(Envelope::class);
        $mailAttachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false, ['foo', 'bar']),
            [],
            Uuid::randomHex()
        );

        $mail->setMailAttachmentsConfig($mailAttachmentsConfig);

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $attachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $attachmentsBuilder
            ->expects($this->once())
            ->method('buildAttachments')
            ->with(
                $mailAttachmentsConfig->getContext(),
                $mailAttachmentsConfig->getMailTemplate(),
                $mailAttachmentsConfig->getExtension(),
                $mailAttachmentsConfig->getEventConfig(),
                $mailAttachmentsConfig->getOrderId()
            )
            ->willReturn([
                ['id' => 'foo', 'documentId' => 'foo', 'content' => 'foo', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
                ['id' => 'bar', 'documentId' => 'bar', 'content' => 'bar', 'fileName' => 'bar', 'mimeType' => 'baz/asd'],
            ]);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('update')
            ->with([
                ['id' => 'foo', 'sent' => true],
                ['id' => 'bar', 'sent' => true],
            ], Context::createDefaultContext());

        $decorator = new MailerTransportDecorator(
            $this->decorated,
            $attachmentsBuilder,
            $this->filesystem,
            $documentRepository
        );

        $decorator->send($mail, $envelope);

        $attachments = $mail->getAttachments();
        static::assertCount(2, $attachments);

        static::assertSame('foo', $attachments[0]->getBody());
        static::assertSame('bar', $attachments[1]->getBody());

        static::assertSame([], $mailAttachmentsConfig->getExtension()->getDocumentIds());
    }

    public function testMailerTransportDecoratorMarksDocumentV2AttachmentsWithDistinctFileIdsAsSent(): void
    {
        $documentId = Uuid::randomHex();
        $pdfFileId = Uuid::randomHex();
        $htmlFileId = Uuid::randomHex();

        $mail = new Mail();
        $envelope = static::createStub(Envelope::class);
        $mailAttachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false, [$documentId]),
            [],
            Uuid::randomHex()
        );

        $mail->setMailAttachmentsConfig($mailAttachmentsConfig);

        $this->decorated->expects($this->once())->method('send')->with($mail, $envelope);

        $attachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $attachmentsBuilder
            ->expects($this->once())
            ->method('buildAttachments')
            ->willReturn([
                ['id' => $pdfFileId, 'documentId' => $documentId, 'content' => 'pdf', 'fileName' => 'invoice.pdf', 'mimeType' => 'application/pdf'],
                ['id' => $htmlFileId, 'documentId' => $documentId, 'content' => 'html', 'fileName' => 'invoice.html', 'mimeType' => 'text/html'],
            ]);

        $documentRepository = $this->createMock(EntityRepository::class);
        $documentRepository
            ->expects($this->once())
            ->method('update')
            ->with([
                ['id' => $documentId, 'sent' => true],
            ], Context::createDefaultContext());

        $decorator = new MailerTransportDecorator(
            $this->decorated,
            $attachmentsBuilder,
            $this->filesystem,
            $documentRepository
        );

        $decorator->send($mail, $envelope);
    }
}
