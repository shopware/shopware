<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Event;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class HttpCacheCookieEvent
{
    /**
     * @param array<array-key, mixed> $parts
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        private array $parts
    ) {
    }

    public function get(string $key): ?string
    {
        return $this->parts[$key] ?? null;
    }

    public function add(string $key, string $value): void
    {
        $this->parts[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->parts[$key]);
    }

    /**
     * @return mixed[]
     */
    public function getParts(): array
    {
        return $this->parts;
    }
}
