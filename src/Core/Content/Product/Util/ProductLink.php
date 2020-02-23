<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Util;

use Shopware\Core\Framework\Struct\Struct;

class ProductLink extends Struct
{
    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $salesChannelName;

    /**
     * @var string
     */
    protected $salesChannelDomain;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $productId;

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): self
    {
        $this->salesChannelId = $salesChannelId;

        return $this;
    }

    public function getSalesChannelName(): string
    {
        return $this->salesChannelName;
    }

    public function setSalesChannelName(string $salesChannelName): self
    {
        $this->salesChannelName = $salesChannelName;

        return $this;
    }

    public function getSalesChannelDomain(): string
    {
        return $this->salesChannelDomain;
    }

    public function setSalesChannelDomain(string $salesChannelDomain): self
    {
        $this->salesChannelDomain = $salesChannelDomain;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): self
    {
        $this->productId = $productId;

        return $this;
    }
}
