<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

interface PathnameStrategyInterface
{
    public function getName(): string;

    /**
     * Cleans the shopware media path
     *
     * Eg. 'http//asdfsadf/asdf/media/image/foobar.png' -> '/media/image/foobar.png'
     *     '/var/www/web1/media/image/foobar.png' -> '/media/image/foobar.png'
     */
    public function decode(string $path): string;

    /**
     * Builds the path on the filesystem
     */
    public function encode(string $filename): string;

    /**
     * Checks if the provided path matches the algorithm format
     */
    public function isEncoded(string $path): bool;
}
