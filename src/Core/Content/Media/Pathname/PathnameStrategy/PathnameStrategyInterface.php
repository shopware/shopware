<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Exception\EmptyMediaFilenameException;
use Shopware\Core\Content\Media\Exception\EmptyMediaIdException;

interface PathnameStrategyInterface
{
    public function getName(): string;

    /**
     * Builds the path on the filesystem
     *
     * @throws EmptyMediaFilenameException
     * @throws EmptyMediaIdException
     */
    public function encode(string $filename, string $id): string;
}
