<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('after-sales')]
class MailSendSubscriberConfig extends Struct
{
    /**
     * @var array<string>
     */
    protected array $documentIds = [];

    /**
     * @var array<string>
     */
    protected array $mediaIds = [];

    /**
     * @var array<string>
     */
    protected array $customerMailTemplateTypesToSkip = [];

    /**
     * @param array<string> $documentIds
     * @param array<string> $mediaIds
     * @param array<string> $customerMailTemplateTypesToSkip
     */
    public function __construct(
        protected bool $skip,
        array $documentIds = [],
        array $mediaIds = [],
        array $customerMailTemplateTypesToSkip = []
    ) {
        $this->documentIds = $documentIds;
        $this->mediaIds = $mediaIds;
        $this->customerMailTemplateTypesToSkip = $customerMailTemplateTypesToSkip;
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
     * @return array<string>
     */
    public function getCustomerMailTemplateTypesToSkip(): array
    {
        return $this->customerMailTemplateTypesToSkip;
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

    /**
     * @param array<string> $customerMailTemplateTypesToSkip
     */
    public function setCustomerMailTemplateTypesToSkip(array $customerMailTemplateTypesToSkip): void
    {
        $this->customerMailTemplateTypesToSkip = $customerMailTemplateTypesToSkip;
    }
}
