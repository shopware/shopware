<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

class WriteProtected extends Flag
{
    /**
     * @var array[string]bool
     */
    private $allowedOrigins = [];

    public function __construct(string ...$allowedOrigins)
    {
        foreach ($allowedOrigins as $origin) {
            $this->allowedOrigins[$origin] = true;
        }
    }

    public function getAllowedOrigins(): array
    {
        return array_keys($this->allowedOrigins);
    }

    public function isAllowed(string $origin): bool
    {
        return isset($this->allowedOrigins[$origin]);
    }
}
