<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

/**
 * @category  Shopware\Core
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
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
    public function addExtension(string $name, ?Struct $extension): void
    {
        $this->extensions[$name] = $extension;
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
        if (isset($this->extensions[$name])) {
            return $this->extensions[$name];
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
