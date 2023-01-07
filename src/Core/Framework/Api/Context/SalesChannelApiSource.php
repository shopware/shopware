<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

/**
 * @package core
 */
class SalesChannelApiSource implements ContextSource
{
    public string $type = 'sales-channel';

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
