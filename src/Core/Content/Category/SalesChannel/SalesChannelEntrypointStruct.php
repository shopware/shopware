<?php
declare(strict_types=1);

namespace Shopware\Core\Content\Category\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('inventory')]
class SalesChannelEntrypointStruct extends Struct
{
    /**
     * @var string
     */
    protected $entrypoint;

    /**
     * @var string
     */
    protected $categoryId;

    public function __construct(
        string $entrypoint,
        string $categoryId
    ) {
        $this->entrypoint = $entrypoint;
        $this->categoryId = $categoryId;
    }

    public function getEntrypoint(): string
    {
        return $this->entrypoint;
    }

    public function setEntrypoint(string $entrypoint): void
    {
        $this->entrypoint = $entrypoint;
    }

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_entrypoint';
    }
}
