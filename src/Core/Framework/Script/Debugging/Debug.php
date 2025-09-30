<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Debugging;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class Debug
{
    /**
     * @var array<string|int, mixed>
     */
    protected array $dumps = [];

    public function dump(mixed $value, ?string $key = null): void
    {
        if ($key !== null) {
            $this->dumps[$key] = $value;
        } else {
            $this->dumps[] = $value;
        }
    }

    /**
     * @return array<string|int, mixed>
     */
    public function all(): array
    {
        return $this->dumps;
    }
}
