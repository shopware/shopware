<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Framework\Struct\Struct;

class MailSendSubscriberConfig extends Struct
{
    /**
     * @var bool
     */
    protected $skip;

    /**
     * @var string[]
     */
    protected $documentIds = [];

    /**
     * @var string[]
     */
    protected $mediaIds = [];

    public function __construct(bool $skip, array $documentIds = [], array $mediaIds = [])
    {
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

    public function getDocumentIds(): array
    {
        return $this->documentIds;
    }

    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    public function setDocumentIds(array $documentIds): void
    {
        $this->documentIds = $documentIds;
    }

    public function setMediaIds(array $mediaIds): void
    {
        $this->mediaIds = $mediaIds;
    }
}
