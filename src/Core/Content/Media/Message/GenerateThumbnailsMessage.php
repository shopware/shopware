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

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }
}
