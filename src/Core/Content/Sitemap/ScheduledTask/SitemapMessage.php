<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @package sales-channel
 */
class SitemapMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    private $lastSalesChannelId;

    /**
     * @var string
     */
    private $lastLanguageId;

    /**
     * @var string
     */
    private $lastProvider;

    /**
     * @var int|null
     */
    private $nextOffset;

    /**
     * @var bool
     */
    private $finished;

    /**
     * @internal
     */
    public function __construct(?string $lastSalesChannelId, ?string $lastLanguageId, ?string $lastProvider, ?int $nextOffset, bool $finished)
    {
        $this->lastSalesChannelId = $lastSalesChannelId;
        $this->lastLanguageId = $lastLanguageId;
        $this->lastProvider = $lastProvider;
        $this->nextOffset = $nextOffset;
        $this->finished = $finished;
    }

    public function getLastSalesChannelId(): ?string
    {
        return $this->lastSalesChannelId;
    }

    public function getLastLanguageId(): ?string
    {
        return $this->lastLanguageId;
    }

    public function getLastProvider(): ?string
    {
        return $this->lastProvider;
    }

    public function getNextOffset(): ?int
    {
        return $this->nextOffset;
    }

    public function isFinished(): bool
    {
        return $this->finished;
    }
}
