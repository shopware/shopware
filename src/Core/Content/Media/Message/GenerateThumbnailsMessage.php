<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Message;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('buyers-experience')]
class GenerateThumbnailsMessage implements AsyncMessageInterface
{
    /**
     * @var array<string>
     */
    private array $mediaIds = [];

    private Context $context;

    /**
     * @deprecated tag:v6.6.0 - Property will be removed, use context instead
     */
    private string $contextData;

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->mediaIds;
    }

    /**
     * @param array<string> $mediaIds
     */
    public function setMediaIds(array $mediaIds): void
    {
        $this->mediaIds = $mediaIds;
    }

    public function getContextData(): string
    {
        return $this->contextData;
    }

    public function setContextData(string $contextData): void
    {
        $this->contextData = $contextData;
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed - reason:remove-getter-setter
     */
    public function withContext(Context $context): GenerateThumbnailsMessage
    {
        $this->contextData = serialize($context);

        return $this;
    }

    /**
     * @deprecated tag:v6.6.0 - Will be removed - reason:remove-getter-setter
     */
    public function readContext(): Context
    {
        return unserialize($this->contextData);
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
}
