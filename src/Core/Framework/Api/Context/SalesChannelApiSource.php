<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

use Shopware\Core\Framework\Log\Package;
/**
 * @package core
 */
#[Package('core')]
class SalesChannelApiSource implements ContextSource
{
    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(string $salesChannelId)
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
