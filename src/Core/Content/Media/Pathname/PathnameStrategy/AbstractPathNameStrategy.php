<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Pathname\PathnameStrategy;

use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.6.0 - Use AbstractMediaPathStrategy instead
 */
#[Package('buyers-experience')]
abstract class AbstractPathNameStrategy implements PathnameStrategyInterface
{
    /**
     * @var array<string, string>
     */
    private array $blacklist = [
        'ad' => 'g0',
    ];

    /**
     * {@inheritdoc}
     */
    public function generatePhysicalFilename(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );
        $filenameSuffix = '';
        if ($thumbnail !== null) {
            $filenameSuffix = sprintf('_%dx%d', $thumbnail->getWidth(), $thumbnail->getHeight());
        }

        $extension = $media->getFileExtension() ? '.' . $media->getFileExtension() : '';

        return $media->getFileName() . $filenameSuffix . $extension;
    }

    /**
     * {@inheritdoc}
     */
    public function generatePathCacheBuster(MediaEntity $media, ?MediaThumbnailEntity $thumbnail = null): ?string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );
        $uploadedAt = $media->getUploadedAt();

        if ($uploadedAt === null) {
            return null;
        }

        return (string) $uploadedAt->getTimestamp();
    }

    protected function generateMd5Path(string $fromValue): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );
        $md5hash = md5($fromValue);

        $md5hashSlices = \array_slice(str_split($md5hash, 2), 0, 3);
        $md5hashSlices = array_map(
            fn ($slice) => \array_key_exists($slice, $this->blacklist) ? $this->blacklist[$slice] : $slice,
            $md5hashSlices
        );

        return implode('/', $md5hashSlices);
    }
}
