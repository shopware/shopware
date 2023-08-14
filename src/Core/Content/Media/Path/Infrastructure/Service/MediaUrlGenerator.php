<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Path\Infrastructure\Service;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Path\Contract\Service\AbstractMediaUrlGenerator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal Concrete implementations of this class should not be extended or used as a base class/type hint.
 */
#[Package('content')]
class MediaUrlGenerator extends AbstractMediaUrlGenerator
{
    public function __construct(
        private readonly FilesystemOperator $filesystem
    ) {
    }

    /**
     * @param array<string|int, array{path:string, updatedAt?: \DateTimeInterface|null}> $paths {"some-key" => {"path": "../test.png", "updatedAt": "2020..."}, "id" => {}}
     *
     * @return array<string|int, string> [key => url]
     */
    public function generate(array $paths): array
    {
        $urls = [];
        foreach ($paths as $key => $value) {
            if (!isset($value['path'])) {
                throw MediaException::invalidUrlGeneratorParameter($key);
            }

            $url = $this->filesystem->publicUrl($value['path']);

            if (isset($value['updatedAt']) && $value['updatedAt'] instanceof \DateTimeInterface) {
                $url .= '?' . $value['updatedAt']->getTimestamp();
            }

            $urls[$key] = $url;
        }

        return $urls;
    }
}
