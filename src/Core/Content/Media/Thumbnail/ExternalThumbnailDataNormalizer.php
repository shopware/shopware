<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Thumbnail;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 *
 * Handles deserialization of {@see  ExternalThumbnailData} when used with #[MapRequestPayload].
 */
#[Package('discovery')]
class ExternalThumbnailDataNormalizer implements DenormalizerInterface, NormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const DENORMALIZE_ALREADY_CALLED = self::class . '::DENORMALIZE_ALREADY_CALLED';
    private const NORMALIZE_ALREADY_CALLED = self::class . '::NORMALIZE_ALREADY_CALLED';

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): ExternalThumbnailData
    {
        if (!\is_array($data)) {
            throw MediaException::invalidThumbnailData(
                'Thumbnail data must be an object with "url", "width" and "height" fields'
            );
        }

        if (!isset($data['url'], $data['width'], $data['height'])) {
            throw MediaException::invalidThumbnailData(
                'Each thumbnail must have "url", "width" and "height" fields'
            );
        }
        $width = (int) $data['width'];
        $height = (int) $data['height'];

        if ($width <= 0) {
            throw MediaException::invalidDimension('width', $width);
        }
        if ($height <= 0) {
            throw MediaException::invalidDimension('height', $height);
        }

        return new ExternalThumbnailData(
            $data['url'],
            $width,
            $height
        );
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === ExternalThumbnailData::class && !isset($context[self::DENORMALIZE_ALREADY_CALLED]);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [ExternalThumbnailData::class => true];
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::NORMALIZE_ALREADY_CALLED] = true;

        return $this->normalizer->normalize($data, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof ExternalThumbnailData && !isset($context[self::NORMALIZE_ALREADY_CALLED]);
    }
}
