<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct\Serializer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @deprecated tag:v6.6.0 - Will be removed, as it was not used
 */
#[Package('core')]
class StructDecoder implements DecoderInterface
{
    /**
     * @return array|mixed
     */
    public function decode(string $data, string $format, array $context = [])
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $this->format($data);
    }

    public function supportsDecoding(string $format): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return $format === 'struct';
    }

    private function format(string $decoded)
    {
        return $decoded;
    }
}
