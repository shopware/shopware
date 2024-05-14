<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('core')]
class HttpCacheKeyEvent
{
    /**
     * @param array<string, string> $parts - Contains a associative array of all parts which are
     *
     * @examples $parts = [
     *  'uri' => 'https://www.my-domain.com/shoes',
     *  'sw-cache-cookie' => '......',
     * ]
     */
    public function __construct(
        public readonly Request $request,
        private array $parts = []
    ) {
    }

    public function has(string $key): bool
    {
        return isset($this->parts[$key]);
    }

    public function get(string $key): string
    {
        return $this->parts[$key];
    }

    /**
     * @return array<string, string>
     */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function remove(string ...$key): self
    {
        foreach ($key as $k) {
            unset($this->parts[$k]);
        }

        return $this;
    }

    public function add(string $key, string $value): self
    {
        $this->parts[$key] = $value;

        return $this;
    }

    public function clear(): self
    {
        $this->parts = [];

        return $this;
    }
}
