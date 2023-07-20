<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Implementation;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractUrlGenerator;

/**
 * @internal Concrete implementations of this class should not be extended or used as a base class/type hint.
 */
class UrlGenerator extends AbstractUrlGenerator
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    public function buildRelative(array $paths): array
    {
        return $paths;
    }

    public function buildAbsolute(array $paths): array
    {
        return array_map(function (string $path): string {
            return $this->filesystem->publicUrl($path);
        }, $paths);
    }
}
