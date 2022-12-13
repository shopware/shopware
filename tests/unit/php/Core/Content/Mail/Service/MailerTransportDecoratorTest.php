<?php declare(strict_types=1);

namespace unit\php\Core\Content\Mail\Service;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailAttachmentsBuilder;
use Shopware\Core\Content\Mail\Service\MailAttachmentsConfig;
use Shopware\Core\Content\Mail\Service\MailerTransportDecorator;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/**
 * @internal
 * @covers \Shopware\Core\Content\Mail\Service\MailerTransportDecorator
 */
class MailerTransportDecoratorTest extends TestCase
{
    /**
     * @var MockObject|TransportInterface
     */
    private $decorated;

    /**
     * @var MockObject|MailAttachmentsBuilder
     */
    private $attachmentsBuilder;

    /**
     * @var MockObject|FilesystemOperator
     */
    private $filesystem;

    /**
     * @var MockObject|EntityRepository
     */
    private $documentRepository;

    private MailerTransportDecorator $decorator;

    public function setUp(): void
    {
        $this->decorated = $this->createMock(TransportInterface::class);
        $this->attachmentsBuilder = $this->createMock(MailAttachmentsBuilder::class);
        $this->filesystem = $this->createMock(FilesystemOperator::class);
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
        $mail = $this->createMock(Mail::class);
        $envelope = $this->createMock(Envelope::class);

        $mail->expects(static::once())->method('getAttachmentUrls')->willReturn(['foo', 'bar']);

        $mail
            ->expects(static::exactly(2))
            ->method('attach')
            ->withConsecutive(['foo', 'foo', 'foo'], ['bar', 'bar', 'bar']);

        $this->decorated->expects(static::once())->method('send')->with($mail, $envelope);

        $this->filesystem
            ->expects(static::exactly(2))
            ->method('read')
            ->withConsecutive(['foo'], ['bar'])
            ->willReturnOnConsecutiveCalls('foo', 'bar');

        $this->filesystem
            ->expects(static::exactly(2))
            ->method('mimeType')
            ->withConsecutive(['foo'], ['bar'])
            ->willReturnOnConsecutiveCalls('foo', 'bar');

        $this->decorator->send($mail, $envelope);
    }

    public function testMailerTransportDecoratorWithBuildAttachments(): void
    {
        $mail = $this->createMock(Mail::class);
        $envelope = $this->createMock(Envelope::class);
        $mailAttachmentsConfig = new MailAttachmentsConfig(
            Context::createDefaultContext(),
            new MailTemplateEntity(),
            new MailSendSubscriberConfig(false, ['foo', 'bar']),
            [],
            Uuid::randomHex()
        );

        $mail->expects(static::once())->method('getAttachmentUrls')->willReturn([]);
        $mail
            ->expects(static::once())
            ->method('getMailAttachmentsConfig')
            ->willReturn($mailAttachmentsConfig);

        $mail
            ->expects(static::exactly(2))
            ->method('attach')
            ->with('foo', 'bar', 'baz');

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
                ['id' => 'foo', 'content' => 'foo', 'fileName' => 'bar', 'mimeType' => 'baz'],
                ['id' => 'bar', 'content' => 'foo', 'fileName' => 'bar', 'mimeType' => 'baz'],
            ]);

        $this->documentRepository
            ->expects(static::once())
            ->method('update')
            ->with([
                ['id' => 'foo', 'sent' => true],
                ['id' => 'bar', 'sent' => true],
            ], Context::createDefaultContext());

        $this->decorator->send($mail, $envelope);
    }
}
