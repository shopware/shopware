<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface ExtendableInterface
{
    /**
     * Adds a new extension struct into the class storage.
     * The passed name is used as unique identifier and has to be stored too.
     */
    public function addExtension(string $name, Struct $extension): void;

    /**
     * @param Struct[] $extensions
     */
    public function addExtensions(array $extensions): void;

    public function getExtension(string $name): ?Struct;

    public function hasExtension(string $name): bool;

    /**
     * Returns all stored extension structures of this class.
     * The array has to be an associated array with name and extension instance.
     *
     * @return Struct[]
     */
    public function getExtensions(): array;
}
