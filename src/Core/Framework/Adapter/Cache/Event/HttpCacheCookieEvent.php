<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Event;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class HttpCacheCookieEvent
{
    /**
     * @param array<string, string> $parts
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

    public function getParts(): array
    {
        return $this->parts;
    }
}
