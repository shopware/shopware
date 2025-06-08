<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailSendSubscriberConfig extends Struct
{
    protected bool $skip;

    /**
     * @var array<string>
     */
    protected array $documentIds = [];

    /**
     * @var array<string>
     */
    protected array $mediaIds = [];

    /**
     * @param array<string> $documentIds
     * @param array<string> $mediaIds
     */
    public function __construct(
        bool $skip,
        array $documentIds = [],
        array $mediaIds = []
    ) {
        $this->skip = $skip;
        $this->documentIds = $documentIds;
        $this->mediaIds = $mediaIds;
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
