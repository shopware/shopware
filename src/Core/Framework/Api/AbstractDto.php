<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractDto
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    public ?array $extensions;

    /**
     * Checks whether a property is initialized, including an explicit null value.
     */
    public function has(string $property): bool
    {
        return \array_key_exists($property, get_object_vars($this));
    }

    /**
     * @param array<string, mixed> $extension
     */
    public function addExtension(string $name, array $extension): void
    {
        $this->extensions ??= [];
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
        return $this->extensions ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $extensions
     */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
        if ($extensions === []) {
            unset($this->extensions);
        }
    }

    public function removeExtension(string $name): void
    {
        unset($this->extensions[$name]);
        if (($this->extensions ?? []) === []) {
            unset($this->extensions);
        }
    }
}
