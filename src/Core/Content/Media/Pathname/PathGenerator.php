<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Pathname\PathnameStrategy\PathnameStrategyInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Package('content')]
class PathGenerator extends AbstractPathGenerator
{
    private PathnameStrategyInterface $pathnameStrategy;

    /**
     * @internal
     */
    public function __construct(PathnameStrategyInterface $pathnameStrategy)
    {
        $this->pathnameStrategy = $pathnameStrategy;
    }

    public function getDecorated(): AbstractPathGenerator
    {
        throw new DecorationPatternException(self::class);
    }

    public function generatePath(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string
    {
        if ($thumbnail) {
            return $this->toPathString([
                'thumbnail',
                $this->pathnameStrategy->generatePathHash($media),
                $this->pathnameStrategy->generatePathCacheBuster($media),
                $this->pathnameStrategy->generatePhysicalFilename($media, $thumbnail),
            ]);
        }

        return $this->toPathString([
            'media',
            $this->pathnameStrategy->generatePathHash($media),
            $this->pathnameStrategy->generatePathCacheBuster($media),
            $this->pathnameStrategy->generatePhysicalFilename($media),
        ]);
    }

    /**
     * @param mixed[] $parts
     */
    private function toPathString(array $parts): string
    {
        return implode('/', array_filter($parts));
    }
}
