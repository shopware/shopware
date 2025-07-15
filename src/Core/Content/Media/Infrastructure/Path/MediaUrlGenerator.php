<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Infrastructure\Path;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementations of this class should not be extended or used as a base class/type hint.
 */
#[Package('discovery')]
class MediaUrlGenerator extends AbstractMediaUrlGenerator
{
    public function __construct(private readonly FilesystemOperator $filesystem)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $paths): array
    {
        $urls = [];
        foreach ($paths as $key => $value) {
            if (str_starts_with($value->path, 'http')) {
                $url = $value->path;
            } else {
                $encodedPath = $this->encodeFilePath($value->path);
                $url = $this->filesystem->publicUrl($encodedPath);
            }

            if ($value->updatedAt !== null) {
                $url .= '?ts=' . $value->updatedAt->getTimestamp();
            }

            $urls[$key] = $url;
        }

        return $urls;
    }

    private function encodeFilePath(string $filePath): string
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return $filePath;
        }

        $segments = explode('/', $filePath);

        foreach ($segments as $index => $segment) {
            $segments[$index] = rawurlencode($segment);
        }

        return implode('/', $segments);
    }
}
