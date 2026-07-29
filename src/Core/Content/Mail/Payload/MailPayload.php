<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Payload;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class MailPayload
{
    /**
     * @param array<string,string|null> $recipients
     * @param list<string> $documentIds
     * @param list<string> $mediaIds
     * @param array<mixed> $attachments
     * @param array<int|string, array{content: resource|string, fileName: string|null, mimeType: string|null}>|null $binAttachments
     * @param array<string, mixed> $extensions
     * @param string|array<string,string|null>|null $recipientsCc
     * @param string|array<string,string|null>|null $recipientsBcc
     * @param string|array<string,string|null>|null $replyTo
     * @param string|array<string,string|null>|null $returnPath
     */
    public function __construct(
        public array $recipients = [],
        public ?string $contentHtml = null,
        public ?string $contentPlain = null,
        public ?string $subject = null,
        public ?string $senderName = null,
        public ?string $senderMail = null,
        public ?string $senderEmail = null,
        public ?string $salesChannelId = null,
        public array $documentIds = [],
        public array $mediaIds = [],
        public array $attachments = [],
        public ?array $binAttachments = null,
        public array $extensions = [],
        public bool $testMode = false,
        public string|array|null $recipientsCc = null,
        public string|array|null $recipientsBcc = null,
        public string|array|null $replyTo = null,
        public string|array|null $returnPath = null,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $data = [
            'recipients' => $this->recipients,
            'contentHtml' => $this->contentHtml,
            'contentPlain' => $this->contentPlain,
            'subject' => $this->subject,
            'senderName' => $this->senderName,
            'testMode' => $this->testMode,
            'documentIds' => $this->documentIds,
            'mediaIds' => $this->mediaIds,
            'attachments' => $this->attachments,
            'extensions' => $this->extensions,
        ];

        if ($this->senderMail !== null) {
            $data['senderMail'] = $this->senderMail;
        }

        if ($this->senderEmail !== null) {
            $data['senderEmail'] = $this->senderEmail;
        }

        if ($this->salesChannelId !== null) {
            $data['salesChannelId'] = $this->salesChannelId;
        }

        if ($this->binAttachments !== null) {
            $data['binAttachments'] = $this->binAttachments;
        }

        if ($this->recipientsCc !== null) {
            $data['recipientsCc'] = $this->recipientsCc;
        }

        if ($this->recipientsBcc !== null) {
            $data['recipientsBcc'] = $this->recipientsBcc;
        }

        if ($this->replyTo !== null) {
            $data['replyTo'] = $this->replyTo;
        }

        if ($this->returnPath !== null) {
            $data['returnPath'] = $this->returnPath;
        }

        // For backward compatibility reasons, we expose the extensions entries on the root level of the payload array.
        if (!Feature::isActive('v6.8.0.0')) {
            foreach ($this->extensions as $extensionKey => $extensionData) {
                if (\array_key_exists($extensionKey, $data)) {
                    continue;
                }

                $data[$extensionKey] = $extensionData;
            }
        }

        return $data;
    }
}
