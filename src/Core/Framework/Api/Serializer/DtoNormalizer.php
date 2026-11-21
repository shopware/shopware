<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\PropertyNormalizer;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
#[Package('framework')]
final class DtoNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    public function __construct(private readonly PropertyNormalizer $normalizer)
    {
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        $this->normalizer->setSerializer($serializer);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [AbstractDto::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AbstractDto;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, AbstractDto::class, true);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return $this->normalizer->normalize($data, $format, [
            ...$context,
            PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC,
            PropertyNormalizer::SKIP_UNINITIALIZED_VALUES => true,
            PropertyNormalizer::SKIP_NULL_VALUES => false,
            PropertyNormalizer::PRESERVE_EMPTY_OBJECTS => true,
        ]);
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        return $this->normalizer->denormalize($data, $type, $format, [
            ...$context,
            PropertyNormalizer::NORMALIZE_VISIBILITY => PropertyNormalizer::NORMALIZE_PUBLIC,
            PropertyNormalizer::REQUIRE_ALL_PROPERTIES => true,
        ]);
    }
}
