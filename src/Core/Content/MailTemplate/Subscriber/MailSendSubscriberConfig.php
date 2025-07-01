<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailSendSubscriberConfig extends Struct
{
    /**
     * @param array<string> $documentIds
     * @param array<string> $mediaIds
     */
    public function __construct(
        protected bool $skip,
        protected array $documentIds = [],
        protected array $mediaIds = [],
    ) {
    }

    public function skip(): bool
    {
        return $this->skip;
    }

    public function setSkip(bool $skip): void
    {
        $this->skip = $skip;
    }

    /**
     * @return array<string>
     */
    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    /**
     * @param array<string> $documentIds
     */
    public function setDocumentIds(array $documentIds): void
    {
        $this->documentIds = $documentIds;
    }

    /**
     * @param array<string> $mediaIds
     */
    public function setMediaIds(array $mediaIds): void
    {
        $this->mediaIds = $mediaIds;
    }
}
