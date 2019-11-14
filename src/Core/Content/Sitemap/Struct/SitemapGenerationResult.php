<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Struct;

use Shopware\Core\Framework\Struct\Struct;

class SitemapGenerationResult extends Struct
{
    /**
     * @var bool
     */
    private $finish;

    /**
     * @var string|null
     */
    private $provider;

    /**
     * @var int|null
     */
    private $offset;

    /**
     * @var string|null
     */
    private $lastSalesChannelId;

    /**
     * @var string
     */
    private $lastLanguageId;

    public function __construct(bool $finish, ?string $provider, ?int $offset, string $lastSalesChannelId, string $lastLanguageId)
    {
        $this->finish = $finish;
        $this->provider = $provider;
        $this->offset = $offset;
        $this->lastSalesChannelId = $lastSalesChannelId;
        $this->lastLanguageId = $lastLanguageId;
    }

    public function isFinish(): bool
    {
        return $this->finish;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getOffset(): ?int
    {
        return $this->offset;
    }

    public function getLastSalesChannelId(): ?string
    {
        return $this->lastSalesChannelId;
    }

    public function getLastLanguageId(): string
    {
        return $this->lastLanguageId;
    }
}
