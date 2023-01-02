<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @internal
 * @package system-settings
 */
#[Package('system-settings')]
class MailerTransportDecorator implements TransportInterface
{
    private TransportInterface $decorated;

    private MailAttachmentsBuilder $attachmentsBuilder;

    private FilesystemInterface $filesystem;

    private EntityRepositoryInterface $documentRepository;

    public function __construct(
        TransportInterface $decorated,
        MailAttachmentsBuilder $attachmentsBuilder,
        FilesystemInterface $filesystem,
        EntityRepositoryInterface $documentRepository
    ) {
        $this->decorated = $decorated;
        $this->attachmentsBuilder = $attachmentsBuilder;
        $this->filesystem = $filesystem;
        $this->documentRepository = $documentRepository;
    }

    public function __toString(): string
    {
        return $this->decorated->__toString();
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (!$message instanceof Mail) {
            return $this->decorated->send($message, $envelope);
        }

        $attach = Feature::isActive('v6.5.0.0') ? 'attach' : 'embed';

        foreach ($message->getAttachmentUrls() as $url) {
            $message->$attach(
                $this->filesystem->read($url) ?: '',
                basename($url),
                $this->filesystem->getMimetype($url) ?: null
            );
        }

        $config = $message->getMailAttachmentsConfig();

        if (!$config) {
            return $this->decorated->send($message, $envelope);
        }

        $attachments = $this->attachmentsBuilder->buildAttachments(
            $config->getContext(),
            $config->getMailTemplate(),
            $config->getExtension(),
            $config->getEventConfig(),
            $config->getOrderId()
        );

        foreach ($attachments as $attachment) {
            $message->$attach(
                $attachment['content'],
                $attachment['fileName'],
                $attachment['mimeType']
            );
        }

        $sentMessage = $this->decorated->send($message, $envelope);

        $this->setDocumentsSent($attachments, $config->getExtension(), $config->getContext());

        return $sentMessage;
    }

    /**
     * @param array<int, array{id?: string, content: string, fileName: string, mimeType: string|null}> $attachments
     */
    private function setDocumentsSent(array $attachments, MailSendSubscriberConfig $extension, Context $context): void
    {
        $documentAttachments = array_filter($attachments, function (array $attachment) use ($extension) {
            return \in_array($attachment['id'] ?? null, $extension->getDocumentIds(), true);
        });

        $documentAttachments = array_column($documentAttachments, 'id');

        if (empty($documentAttachments)) {
            return;
        }

        $payload = array_map(static function (string $documentId) {
            return [
                'id' => $documentId,
                'sent' => true,
            ];
        }, $documentAttachments);

        $this->documentRepository->update($payload, $context);
    }
}
