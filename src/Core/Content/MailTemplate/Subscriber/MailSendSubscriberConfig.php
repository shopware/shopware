<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailSendSubscriberConfig extends Struct
{
    /**
     * @var list<string>
     */
    protected array $documentIds = [];

    /**
     * @var list<string>
     */
    protected array $mediaIds = [];

    /**
     * @param list<string> $documentIds
     * @param list<string> $mediaIds
     */
    public function __construct(
        protected bool $skip,
        array $documentIds = [],
        array $mediaIds = []
    ) {
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
     * @return list<string>
     */
    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    /**
     * @return list<string>
     */
    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    /**
     * @param list<string> $documentIds
     */
    public function setDocumentIds(array $documentIds): void
    {
        $this->documentIds = $documentIds;
    }

    /**
     * @param list<string> $mediaIds
     */
    public function setMediaIds(array $mediaIds): void
    {
        $this->mediaIds = $mediaIds;
    }
}
