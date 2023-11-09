<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
trait ExtendableTrait
{
    /**
     * Contains an array of extension structs.
     *
     * @var Struct[]
     */
    protected $extensions = [];

    /**
     * Adds a new extension struct into the class storage.
     * The passed name is used as unique identifier and has to be stored too.
     */
    public function addExtension(string $name, Struct $extension): void
    {
        $this->extensions[$name] = $extension;
    }

    /**
     * @param array<string|int, mixed> $extension
     *
     * Adds a new array struct as extension into the class storage.
     * The passed name is used as unique identifier and has to be stored too.
     */
    public function addArrayExtension(string $name, array $extension): void
    {
        $this->extensions[$name] = new ArrayStruct($extension);
    }

    /**
     * @param Struct[] $extensions
     */
    public function addExtensions(array $extensions): void
    {
        foreach ($extensions as $key => $extension) {
            $this->addExtension($key, $extension);
        }
    }

    /**
     * Returns a single extension struct element of this class.
     * The passed name is used as unique identifier.
     */
    public function getExtension(string $name): ?Struct
    {
        return $this->extensions[$name] ?? null;
    }

    /**
     * @template T of Struct
     *
     * @param class-string<T> $type
     *
     * @return T|null
     */
    public function getExtensionOfType(string $name, string $type): ?Struct
    {
        if ($this->hasExtensionOfType($name, $type)) {
            /** @var T $extension */
            $extension = $this->getExtension($name);

            return $extension;
        }

        return null;
    }

    /**
     * Helper function which checks if an associated
     * extension exists.
     */
    public function hasExtension(string $name): bool
    {
        return isset($this->extensions[$name]);
    }

    public function hasExtensionOfType(string $name, string $type): bool
    {
        $extension = $this->getExtension($name);

        if ($extension === null) {
            return false;
        }

        return $extension::class === $type;
    }

    /**
     * Returns all stored extension structures of this class.
     * The array has to be an associated array with name and extension instance.
     *
     * @return array<string, Struct>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @param array<string, Struct> $extensions
     */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }

    public function removeExtension(string $name): void
    {
        if (isset($this->extensions[$name])) {
            unset($this->extensions[$name]);
        }
    }
}
