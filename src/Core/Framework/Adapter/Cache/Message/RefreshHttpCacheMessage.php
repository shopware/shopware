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
     * @param array<string, mixed> $query
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $cookies
     * @param array<string, mixed> $server
     * @param array<string, mixed> $trustedIps
     */
    public function __construct(public string $lockKey, public array $query = [], public array $attributes = [], public array $cookies = [], public array $server = [], public array $trustedIps = [], public int $trustedHeaderSet = Request::HEADER_FORWARDED)
    {
    }
}
