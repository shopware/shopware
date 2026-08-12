<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Request;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractRequest
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private array $extensions = [];

    /**
     * @param array<string, mixed> $extension
     */
    public function addExtension(string $name, array $extension): void
    {
        $this->extensions[$name] = $extension;
    }

    /**
     * @param array<string, array<string, mixed>> $extensions
     */
    public function addExtensions(array $extensions): void
    {
        foreach ($extensions as $name => $extension) {
            $this->addExtension($name, $extension);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getExtension(string $name): ?array
    {
        return $this->extensions[$name] ?? null;
    }

    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @param array<string, array<string, mixed>> $extensions
     */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }

    public function removeExtension(string $name): void
    {
        unset($this->extensions[$name]);
    }
}
