<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

#[Package('after-sales')]
class Mail extends Email
{
    private bool $sensitive = false;

    private ?MailAttachmentsConfig $mailAttachmentsConfig = null;

    /**
     * @var string[]
     */
    private array $attachmentUrls = [];

    /**
     * @return list<mixed>
     */
    public function __serialize(): array
    {
        /** @var list<mixed> $data */
        $data = parent::__serialize();

        $data[] = $this->mailAttachmentsConfig;
        $data[] = $this->attachmentUrls;
        $data[] = $this->sensitive;

        return $data;
    }

    /**
     * @param list<mixed> $data
     */
    public function __unserialize(array $data): void
    {
        if (Feature::isActive('v6.8.0.0')) {
            [$this->mailAttachmentsConfig, $this->attachmentUrls, $this->sensitive] = array_splice($data, -3, 3);
        } else {
            // @deprecated tag:v6.8.0 - Remove support for queued mails without the sensitivity flag.
            $sensitive = end($data);
            if (\is_bool($sensitive)) {
                $this->sensitive = $sensitive;
                array_pop($data);
            }
            [$this->mailAttachmentsConfig, $this->attachmentUrls] = array_splice($data, -2, 2);
        }

        parent::__unserialize($data);
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function setSensitive(bool $sensitive): self
    {
        $this->sensitive = $sensitive;

        return $this;
    }

    public function getMailAttachmentsConfig(): ?MailAttachmentsConfig
    {
        return $this->mailAttachmentsConfig;
    }

    public function setMailAttachmentsConfig(?MailAttachmentsConfig $mailAttachmentsConfig): self
    {
        $this->mailAttachmentsConfig = $mailAttachmentsConfig;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAttachmentUrls(): array
    {
        return $this->attachmentUrls;
    }

    public function addAttachmentUrl(string $url): self
    {
        $this->attachmentUrls[] = $url;

        return $this;
    }
}
