<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Debugging;

class Debug
{
    protected array $dumps = [];

    /**
     * @param mixed $value
     */
    public function dump($value, ?string $key = null): void
    {
        if ($key !== null) {
            $this->dumps[$key] = $value;
        } else {
            $this->dumps[] = $value;
        }
    }

    public function all(): array
    {
        return $this->dumps;
    }
}
