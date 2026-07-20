<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class RefreshHttpCacheMessage implements AsyncMessageInterface
{
    /**
     * @param array<mixed> $query
     * @param array<mixed> $attributes
     * @param array<mixed> $cookies
     * @param array<mixed> $server
     * @param array<mixed> $trustedIps
     */
    public function __construct(public string $lockKey, public array $query = [], public array $attributes = [], public array $cookies = [], public array $server = [], public array $trustedIps = [], public int $trustedHeaderSet = Request::HEADER_FORWARDED)
    {
    }
}
