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

    public function getExtensionOfType(string $name, string $type): ?Struct
    {
        if ($this->hasExtensionOfType($name, $type)) {
            return $this->getExtension($name);
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
        return $this->hasExtension($name) && \get_class($this->getExtension($name)) === $type;
    }

    /**
     * Returns all stored extension structures of this class.
     * The array has to be an associated array with name and extension instance.
     *
     * @return Struct[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

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
