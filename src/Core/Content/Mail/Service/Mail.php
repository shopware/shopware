<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

#[Package('system-settings')]
class Mail extends Email
{
    private ?MailAttachmentsConfig $mailAttachmentsConfig = null;

    /**
     * @var string[]
     */
    private array $attachmentUrls = [];

    /**
     * @return mixed[]
     */
    public function __serialize(): array
    {
        $data = parent::__serialize();

        $data[] = $this->mailAttachmentsConfig;
        $data[] = $this->attachmentUrls;

        return $data;
    }

    /**
     * @param mixed[] $data
     */
    public function __unserialize(array $data): void
    {
        [$this->mailAttachmentsConfig, $this->attachmentUrls] = array_splice($data, -2, 2);

        parent::__unserialize($data);
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
